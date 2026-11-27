<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MusicTest extends TestCase
{
    private function geniusHit(string $title, string $artist): array
    {
        return ['type' => 'song', 'result' => [
            'title' => $title,
            'artist_names' => $artist,
            'primary_artist' => ['name' => $artist],
            'song_art_image_thumbnail_url' => 'https://images.genius.com/x.jpg',
            'url' => 'https://genius.com/'.str_replace(' ', '-', "$artist $title").'-lyrics',
        ]];
    }

    public function test_query_is_required(): void
    {
        $this->getJson('/api/music/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/api/music/search')->assertUnprocessable();
    }

    public function test_lyric_search_attaches_deezer_and_itunes_previews(): void
    {
        config(['services.genius.token' => 'test-token']);

        Http::fake([
            'api.genius.com/*' => Http::response(['response' => ['hits' => [
                $this->geniusHit('Bohemian Rhapsody', 'Queen'),
                ['type' => 'lyric', 'result' => []],
                $this->geniusHit('Rare Song', 'Nobody'),
            ]]]),
            'api.deezer.com/*' => function (Request $r) {
                return str_contains($r['q'], 'Queen')
                    ? Http::response(['data' => [[
                        'preview' => 'https://cdnt-preview.dzcdn.net/q.mp3',
                        'link' => 'https://www.deezer.com/track/1',
                        'album' => ['cover_medium' => 'https://cdn.deezer.com/cover.jpg', 'title' => 'A Night At The Opera'],
                    ]]])
                    : Http::response(['data' => []]);
            },
            'itunes.apple.com/*' => Http::response(['results' => [[
                'previewUrl' => 'https://audio.itunes.apple.com/r.m4a',
                'trackViewUrl' => 'https://music.apple.com/us/album/rare/1?i=2',
                'collectionName' => 'Rare Album',
            ]]]),
        ]);

        $this->getJson('/api/music/search?q='.urlencode('is this the real life'))
            ->assertOk()
            ->assertJsonPath('mode', 'lyrics')
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.title', 'Bohemian Rhapsody')
            ->assertJsonPath('results.0.preview', 'https://cdnt-preview.dzcdn.net/q.mp3')
            ->assertJsonPath('results.0.cover', 'https://cdn.deezer.com/cover.jpg')
            ->assertJsonPath('results.1.preview', 'https://audio.itunes.apple.com/r.m4a')
            ->assertJsonPath('results.1.genius_url', 'https://genius.com/Nobody-Rare-Song-lyrics')
            ->assertJsonPath('results.1.apple_url', 'https://music.apple.com/us/album/rare/1?i=2')
            ->assertJsonPath('results.0.apple_url', null)
            ->assertJsonPath('results.0.album', 'A Night At The Opera')
            ->assertJsonPath('results.0.source', 'Deezer')
            ->assertJsonPath('results.1.album', 'Rare Album')
            ->assertJsonPath('results.1.source', 'iTunes')
            ->assertJsonMissingPath('results.0.lookup_artist');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.genius.com')
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_without_a_token_it_falls_back_to_title_search(): void
    {
        config(['services.genius.token' => null]);

        Http::fake(['api.deezer.com/*' => Http::response(['data' => [[
            'title' => 'Bohemian Rhapsody',
            'artist' => ['name' => 'Queen'],
            'album' => ['cover_medium' => 'https://cdn.deezer.com/cover.jpg'],
            'preview' => 'https://cdnt-preview.dzcdn.net/q.mp3',
            'link' => 'https://www.deezer.com/track/1',
        ]]])]);

        $this->getJson('/api/music/search?q=bohemian')
            ->assertOk()
            ->assertJsonPath('mode', 'title')
            ->assertJsonPath('results.0.artist', 'Queen')
            ->assertJsonPath('results.0.source', 'Deezer');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'genius'));
    }

    public function test_duplicate_tracks_are_merged(): void
    {
        config(['services.genius.token' => null]);

        $track = fn (string $title, string $artist, string $album, string $clip, int $id) => [
            'title' => $title,
            'artist' => ['name' => $artist],
            'album' => ['title' => $album, 'cover_medium' => null],
            'preview' => "https://cdnt-preview.dzcdn.net/api/1/$clip.mp3?hdnea=token$id",
            'link' => "https://www.deezer.com/track/$id",
        ];

        Http::fake(['api.deezer.com/*' => Http::response(['data' => [
            $track('Fuego', 'Aitch', 'Close To Home', 'explicit', 1),
            $track('Fuego', 'Aitch', 'Close  to home', 'clean', 2),      // clean edition: same title/artist/album
            $track('FUEGO', 'Mischa', 'ZONE', 'zone', 3),
            $track('FUEGO', 'Mischa', 'ZONE (Deluxe)', 'zone', 4),       // re-release: same clip, new token
            $track('Fuego', 'Neves17', 'Fuego', 'neves', 5),
        ]])]);

        $this->getJson('/api/music/search?q=fuego')
            ->assertOk()
            ->assertJsonCount(3, 'results')
            ->assertJsonPath('results.0.deezer_url', 'https://www.deezer.com/track/1')
            ->assertJsonPath('results.1.deezer_url', 'https://www.deezer.com/track/3')
            ->assertJsonPath('results.2.artist', 'Neves17');
    }

    public function test_genius_failure_is_a_502(): void
    {
        config(['services.genius.token' => 'test-token']);
        Http::fake(['api.genius.com/*' => Http::response([], 500)]);

        $this->getJson('/api/music/search?q=anything')->assertStatus(502);
    }
}
