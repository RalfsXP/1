<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Music prototype (/music): find songs from a lyric snippet and attach a 30-second preview.
 *
 * 1. Genius search (GENIUS_ACCESS_TOKEN, free client token) matches lyric text → title + artist.
 *    Without a token it falls back to a plain Deezer search (titles/artists only, mode "title").
 * 2. Each hit is looked up on Deezer (no key) for a preview MP3; iTunes Search (no key) fills the gaps.
 * Lyrics themselves are never returned (copyright) — only metadata, the preview and page links. apple_url is filled only
 * when iTunes was asked anyway; the page looks it up itself (iTunes allows CORS) when "Open in" opens.
 * album + source (Deezer | iTunes | Genius = where the album and preview came from) tell same-named songs apart;
 * true duplicates (explicit + clean editions, re-releases of one recording) are merged — see unique().
 */
class MusicController extends Controller
{
    private const LIMIT = 6;
    // Title search asks Deezer for more than it shows, so the list still fills after duplicates are merged.
    private const FETCH = 20;
    private const TIMEOUT = 6;

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:300']]);
        $q = trim(preg_replace('/\s+/u', ' ', $data['q']));

        $token = config('services.genius.token');
        if (! $token) {
            return response()->json(['mode' => 'title', 'results' => $this->deezerSearch($q)]);
        }

        try {
            $res = Http::withToken($token)->acceptJson()->timeout(self::TIMEOUT)
                ->get('https://api.genius.com/search', ['q' => $q]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Lyrics search is unavailable right now.'], 502);
        }

        if (! $res->successful()) {
            return response()->json(['message' => 'Lyrics search is unavailable right now.'], 502);
        }

        $songs = collect($res->json('response.hits', []))
            ->where('type', 'song')
            ->pluck('result')
            ->take(self::LIMIT)
            ->map(fn (array $s) => [
                'title' => $s['title'] ?? '',
                'artist' => $s['artist_names'] ?? ($s['primary_artist']['name'] ?? ''),
                'lookup_artist' => $s['primary_artist']['name'] ?? ($s['artist_names'] ?? ''),
                'cover' => $s['song_art_image_thumbnail_url'] ?? null,
                'preview' => null,
                'genius_url' => $s['url'] ?? null,
                'deezer_url' => null,
                'apple_url' => null,
                'album' => null,
                'source' => 'Genius',
            ])
            ->values()
            ->all();

        return response()->json(['mode' => 'lyrics', 'results' => $this->unique($this->attachPreviews($songs))]);
    }

    /** Deezer first (strict artist + track query), iTunes for whatever is still without a preview. */
    private function attachPreviews(array $songs): array
    {
        if (! $songs) {
            return [];
        }

        $deezer = $this->pool($songs, fn (Pool $pool, array $s, int $i) => $pool->as((string) $i)
            ->acceptJson()->timeout(self::TIMEOUT)
            ->get('https://api.deezer.com/search', [
                'q' => sprintf('artist:"%s" track:"%s"', $s['lookup_artist'], $s['title']),
                'limit' => 1,
            ]));

        foreach ($songs as $i => &$song) {
            $track = $deezer[$i]?->json('data.0');
            if ($track && ! empty($track['preview'])) {
                $song['preview'] = $track['preview'];
                $song['deezer_url'] = $track['link'] ?? null;
                $song['cover'] = $track['album']['cover_medium'] ?? $song['cover'];
                $song['album'] = $track['album']['title'] ?? null;
                $song['source'] = 'Deezer';
            }
        }
        unset($song);

        $missing = array_filter($songs, fn ($s) => ! $s['preview']);
        if ($missing) {
            $itunes = $this->pool($missing, fn (Pool $pool, array $s, int $i) => $pool->as((string) $i)
                ->acceptJson()->timeout(self::TIMEOUT)
                ->get('https://itunes.apple.com/search', [
                    'term' => $s['lookup_artist'].' '.$s['title'],
                    'entity' => 'song',
                    'limit' => 1,
                ]));

            foreach ($missing as $i => $_) {
                $track = $itunes[$i]?->json('results.0');
                if ($track && ! empty($track['previewUrl'])) {
                    $songs[$i]['preview'] = $track['previewUrl'];
                    $songs[$i]['cover'] ??= $track['artworkUrl100'] ?? null;
                    $songs[$i]['apple_url'] = $track['trackViewUrl'] ?? null;
                    $songs[$i]['album'] = $track['collectionName'] ?? null;
                    $songs[$i]['source'] = 'iTunes';
                }
            }
        }

        return array_map(function ($s) {
            unset($s['lookup_artist']);

            return $s;
        }, $songs);
    }

    /** No Genius token: plain Deezer search (matches titles and artists, not lyrics). */
    private function deezerSearch(string $q): array
    {
        try {
            $res = Http::acceptJson()->timeout(self::TIMEOUT)
                ->get('https://api.deezer.com/search', ['q' => $q, 'limit' => self::FETCH]);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return $this->unique(collect($res->json('data', []))
            ->map(fn (array $t) => [
                'title' => $t['title'] ?? '',
                'artist' => $t['artist']['name'] ?? '',
                'cover' => $t['album']['cover_medium'] ?? null,
                'preview' => $t['preview'] ?: null,
                'genius_url' => null,
                'deezer_url' => $t['link'] ?? null,
                'apple_url' => null,
                'album' => $t['album']['title'] ?? null,
                'source' => 'Deezer',
            ])
            ->values()
            ->all());
    }

    /**
     * Drops repeats, keeping the first (most relevant) one, up to LIMIT. A repeat = same title + artist + album
     * (Deezer lists an album's explicit and clean editions as separate tracks) or the same preview file (one
     * recording released on several albums / re-released — Deezer gives them the same clip).
     */
    private function unique(array $songs): array
    {
        $norm = fn (?string $v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        $seen = [];
        $out = [];

        foreach ($songs as $song) {
            $keys = ['meta:'.$norm($song['title']).'|'.$norm($song['artist']).'|'.$norm($song['album'])];
            if ($song['preview']) {
                $keys[] = 'clip:'.strtok($song['preview'], '?'); // the query part is a per-request signed token
            }
            if (array_intersect($keys, array_keys($seen))) {
                continue;
            }
            foreach ($keys as $key) {
                $seen[$key] = true;
            }
            $out[] = $song;
            if (count($out) === self::LIMIT) {
                break;
            }
        }

        return $out;
    }

    /**
     * Runs one request per item at once; returns [index => successful Response|null].
     *
     * @param  callable(Pool, array, int): mixed  $make
     */
    private function pool(array $items, callable $make): array
    {
        try {
            $responses = Http::pool(function (Pool $pool) use ($items, $make) {
                foreach ($items as $i => $item) {
                    $make($pool, $item, $i);
                }
            });
        } catch (Throwable $e) {
            report($e);
            $responses = [];
        }

        $out = [];
        foreach ($items as $i => $_) {
            $r = $responses[(string) $i] ?? null;
            $out[$i] = $r instanceof Response && $r->successful() ? $r : null;
        }

        return $out;
    }
}
