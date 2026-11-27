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
 * Music prototype (/music): find songs from a lyric snippet (or a song name) and attach a 30-second preview.
 *
 * by=lyrics (default; needs GENIUS_ACCESS_TOKEN, a free client token): one Genius search, in Genius's own order.
 *   (Tried: searching several variants of the phrase + rank fusion + a page-view boost to tolerate a wrong word —
 *   the owner found the results worse, so it was taken out again.)
 * by=title: a plain Deezer search (titles + artists; also the fallback without a token → mode "title").
 * Each lyric hit is looked up on Deezer (no key) for a preview MP3; iTunes Search (no key) fills the gaps.
 * Lyrics themselves are never returned (copyright) — only metadata, the preview and page links. apple_url is filled only
 * when iTunes was asked anyway; the page looks it up itself (iTunes allows CORS) when "Open in" opens.
 * album + source (Deezer | iTunes | Genius = where the album and preview came from) tell same-named songs apart;
 * true duplicates (explicit + clean editions, re-releases of one recording) are merged — see unique().
 * Paged: 5 songs per page (?page=1, 2, …; has_more says whether "Load more" can fetch another). Lyrics: Genius gives
 * up to 20 songs, each page looks up previews only for its own 5. Song name: one Deezer search of 50, merged, sliced.
 */
class MusicController extends Controller
{
    private const PAGE = 5;
    // Song name: Deezer results fetched once per request (merged, then sliced into pages).
    private const DEEZER_FETCH = 50;
    private const TIMEOUT = 6;

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:300'],
            'by' => ['nullable', 'in:lyrics,title'],
            'page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $q = trim(preg_replace('/\s+/u', ' ', $data['q']));
        $page = (int) ($data['page'] ?? 1);
        $offset = ($page - 1) * self::PAGE;

        $token = config('services.genius.token');
        if (($data['by'] ?? 'lyrics') === 'title' || ! $token) {
            $songs = $this->deezerSearch($q);

            return response()->json([
                'mode' => 'title',
                'page' => $page,
                'results' => array_slice($songs, $offset, self::PAGE),
                'has_more' => count($songs) > $offset + self::PAGE,
            ]);
        }

        $songs = $this->lyricSongs($q, $token);
        if ($songs === null) {
            return response()->json(['message' => 'Lyrics search is unavailable right now.'], 502);
        }

        return response()->json([
            'mode' => 'lyrics',
            'page' => $page,
            'results' => $this->unique($this->attachPreviews(array_slice($songs, $offset, self::PAGE))),
            'has_more' => count($songs) > $offset + self::PAGE,
        ]);
    }

    /** One Genius search → all its song hits (up to 20) in the shape attachPreviews() expects, or null when Genius didn't answer. */
    private function lyricSongs(string $q, string $token): ?array
    {
        try {
            $res = Http::withToken($token)->acceptJson()->timeout(self::TIMEOUT)
                ->get('https://api.genius.com/search', ['q' => $q, 'per_page' => 20]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $res->successful()) {
            return null;
        }

        return collect($res->json('response.hits', []))
            ->where('type', 'song')
            ->pluck('result')
            // Genius's own translation pages ("Genius English Translations", "Genius Farsi Translations"…) aren't songs.
            ->reject(fn ($s) => preg_match('/^Genius\b/u', $s['primary_artist']['name'] ?? ''))
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
                // iTunes always answers with something: only take it when the artist matches.
                if ($track && ! empty($track['previewUrl'])
                    && $this->sameArtist($songs[$i]['lookup_artist'], $track['artistName'] ?? '')) {
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

    /** Loose artist match: one name contains the other, or they share a word of 3+ letters ("Queen" ~ "Queen & David Bowie"). */
    private function sameArtist(string $a, string $b): bool
    {
        $words = fn (string $v) => array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($v)) ?: [],
            fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, ['the', 'and', 'feat', 'with'], true),
        );
        $na = implode(' ', $words($a));
        $nb = implode(' ', $words($b));

        return $na !== '' && $nb !== ''
            && (str_contains($na, $nb) || str_contains($nb, $na) || array_intersect($words($a), $words($b)));
    }

    /** No Genius token: plain Deezer search (matches titles and artists, not lyrics). */
    private function deezerSearch(string $q): array
    {
        try {
            $res = Http::acceptJson()->timeout(self::TIMEOUT)
                ->get('https://api.deezer.com/search', ['q' => $q, 'limit' => self::DEEZER_FETCH]);
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
     * Drops repeats, keeping the first (most relevant) one. A repeat = same title + artist + album
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
