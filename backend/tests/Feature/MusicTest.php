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
                'artistName' => 'Nobody & Friends',
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

    public function test_lyric_search_is_one_genius_search_in_its_own_order(): void
    {
        config(['services.genius.token' => 'test-token']);

        Http::fake([
            'api.genius.com/*' => Http::response(['response' => ['hits' => [
                $this->geniusHit('First', 'One'),
                $this->geniusHit('Second', 'Two'),
            ]]]),
            'api.deezer.com/*' => Http::response(['data' => []]),
            'itunes.apple.com/*' => Http::response(['results' => []]),
        ]);

        $this->getJson('/api/music/search?q='.urlencode('look at the stars see how they shine'))
            ->assertOk()
            ->assertJsonPath('results.0.title', 'First')
            ->assertJsonPath('results.1.title', 'Second');

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.genius.com')));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.genius.com')
            && $r['q'] === 'look at the stars see how they shine' && $r['per_page'] == 20);
    }

    public function test_translation_pages_and_wrong_itunes_clips_are_left_out(): void
    {
        config(['services.genius.token' => 'test-token']);

        $hit = fn (string $title, string $artist) => ['type' => 'song', 'result' => [
            'id' => crc32($title),
            'title' => $title,
            'artist_names' => $artist,
            'primary_artist' => ['name' => $artist],
            'url' => 'https://genius.com/'.crc32($title),
        ]];

        Http::fake([
            'api.genius.com/*' => Http::response(['response' => ['hits' => [
                $hit('Heart of Darkness (Section II)', 'Joseph Conrad'),          // a book: no store has it
                $hit('Hello (Traducción)', 'Genius Traducciones al Español'),    // a translation page
                $hit('Hello', 'Adele'),
            ]]]),
            'api.deezer.com/*' => Http::response(['data' => []]),
            'itunes.apple.com/*' => function (Request $r) {
                return str_contains($r['term'], 'Adele')
                    ? Http::response(['results' => [['previewUrl' => 'https://a/hello.m4a', 'artistName' => 'Adele']]])
                    : Http::response(['results' => [['previewUrl' => 'https://a/other.m4a', 'artistName' => 'Some Narrator']]]);
            },
        ]);

        $this->getJson('/api/music/search?q='.urlencode('hello from the other side'))
            ->assertOk()
            ->assertJsonCount(2, 'results')                             // the translation page is gone
            ->assertJsonPath('results.0.title', 'Heart of Darkness (Section II)')
            ->assertJsonPath('results.0.preview', null)                // the narrator's clip was not taken
            ->assertJsonPath('results.1.title', 'Hello')
            ->assertJsonPath('results.1.preview', 'https://a/hello.m4a');
    }

    public function test_song_name_filter_uses_deezer_even_with_a_token_and_pages_by_five(): void
    {
        config(['services.genius.token' => 'test-token']);

        Http::fake(['api.deezer.com/*' => Http::response(['data' => collect(range(1, 12))->map(fn ($i) => [
            'title' => "Song $i",
            'artist' => ['name' => 'Band'],
            'album' => ['title' => "Album $i", 'cover_medium' => null],
            'preview' => "https://cdnt-preview.dzcdn.net/$i.mp3",
            'link' => "https://www.deezer.com/track/$i",
        ])->all()])]);

        $this->getJson('/api/music/search?by=title&q=song')
            ->assertOk()
            ->assertJsonPath('mode', 'title')
            ->assertJsonPath('page', 1)
            ->assertJsonCount(5, 'results')
            ->assertJsonPath('results.0.title', 'Song 1')
            ->assertJsonPath('has_more', true);

        $this->getJson('/api/music/search?by=title&q=song&page=3')
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.title', 'Song 11')
            ->assertJsonPath('has_more', false);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'genius'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.deezer.com') && $r['limit'] == 50);

        $this->getJson('/api/music/search?by=artist&q=song')->assertUnprocessable()->assertJsonValidationErrors('by');
        $this->getJson('/api/music/search?q=song&page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_lyric_pages_only_look_up_previews_for_their_own_five(): void
    {
        config(['services.genius.token' => 'test-token']);

        Http::fake([
            'api.genius.com/*' => Http::response(['response' => ['hits' => collect(range(1, 12))
                ->map(fn ($i) => $this->geniusHit("Song $i", "Artist $i"))->all()]]),
            'api.deezer.com/*' => Http::response(['data' => []]),
            'itunes.apple.com/*' => Http::response(['results' => []]),
        ]);

        $this->getJson('/api/music/search?q=some+lyric&page=2')
            ->assertOk()
            ->assertJsonPath('mode', 'lyrics')
            ->assertJsonPath('page', 2)
            ->assertJsonCount(5, 'results')
            ->assertJsonPath('results.0.title', 'Song 6')
            ->assertJsonPath('has_more', true);

        $this->assertCount(5, Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.deezer.com')));

        $this->getJson('/api/music/search?q=some+lyric&page=3')
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.1.title', 'Song 12')
            ->assertJsonPath('has_more', false);
    }

    public function test_genius_failure_is_a_502(): void
    {
        config(['services.genius.token' => 'test-token']);
        Http::fake(['api.genius.com/*' => Http::response([], 500)]);

        $this->getJson('/api/music/search?q=anything')->assertStatus(502);
    }
}
