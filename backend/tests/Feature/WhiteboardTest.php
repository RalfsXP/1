<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhiteboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function spa(?User $as = null): static
    {
        return $this->actingAs($as ?? $this->user)->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }

    private function scene(): array
    {
        return ['version' => '7.4.0', 'objects' => [['type' => 'Path', 'stroke' => '#000000', 'path' => [['M', 0, 0], ['L', 10, 10]]]]];
    }

    public function test_whiteboards_require_authentication(): void
    {
        $this->getJson('/api/whiteboards')->assertUnauthorized();
    }

    public function test_a_user_can_create_save_load_rename_and_delete_a_board(): void
    {
        $id = $this->spa()->withHeader('Accept-Language', 'lv')->postJson('/api/whiteboards')
            ->assertCreated()
            ->assertJsonPath('title', 'Tāfele bez nosaukuma')
            ->json('id');

        $this->spa()->patchJson("/api/whiteboards/{$id}", [
            'data' => $this->scene(),
            'preview' => 'data:image/webp;base64,AAAA',
        ])->assertOk()->assertJsonMissingPath('data');

        $this->spa()->getJson("/api/whiteboards/{$id}")
            ->assertOk()
            ->assertJsonPath('data.objects.0.stroke', '#000000')
            ->assertJsonPath('preview', 'data:image/webp;base64,AAAA');

        $this->spa()->patchJson("/api/whiteboards/{$id}", ['title' => 'Idejas'])->assertOk()->assertJsonPath('title', 'Idejas');

        $this->spa()->deleteJson("/api/whiteboards/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('whiteboards', ['id' => $id]);
    }

    public function test_the_list_is_newest_first_and_leaves_out_scene_data(): void
    {
        $old = $this->user->whiteboards()->create(['title' => 'Old', 'data' => $this->scene()]);
        $old->forceFill(['updated_at' => now()->subDay()])->save();
        $this->user->whiteboards()->create(['title' => 'New']);
        User::factory()->create()->whiteboards()->create(['title' => 'Not mine']);

        $this->spa()->getJson('/api/whiteboards')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'New')
            ->assertJsonPath('1.title', 'Old')
            ->assertJsonMissingPath('1.data');
    }

    public function test_other_users_boards_are_not_found(): void
    {
        $board = $this->user->whiteboards()->create(['title' => 'Mine']);
        $intruder = User::factory()->create();

        $this->spa($intruder)->getJson("/api/whiteboards/{$board->id}")->assertNotFound();
        $this->spa($intruder)->patchJson("/api/whiteboards/{$board->id}", ['title' => 'Taken'])->assertNotFound();
        $this->spa($intruder)->deleteJson("/api/whiteboards/{$board->id}")->assertNotFound();
        $this->assertModelExists($board);
    }

    public function test_oversized_scenes_and_bad_previews_are_rejected(): void
    {
        $board = $this->user->whiteboards()->create(['title' => 'Big']);
        $huge = ['objects' => [['type' => 'Textbox', 'text' => str_repeat('x', 2 * 1024 * 1024)]]];

        $this->spa()->patchJson("/api/whiteboards/{$board->id}", ['data' => $huge])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data']);

        $this->spa()->withHeader('Accept-Language', 'lv')
            ->patchJson("/api/whiteboards/{$board->id}", ['preview' => 'javascript:alert(1)', 'title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preview', 'title'])
            ->assertJsonPath('errors.title.0', 'Lauks nosaukums ir obligāts.');
    }
}
