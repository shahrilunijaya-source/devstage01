<?php

declare(strict_types=1);

namespace Tests\Feature\Releases;

use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function release(string $version = '1.2.0'): Release
    {
        $release = Release::create([
            'version' => $version,
            'title' => "v{$version}",
            'notes' => ['new' => ['Shiny feature'], 'fixed' => ['A bug'], 'improved' => []],
            'git_sha' => str_repeat('a', 40),
            'released_at' => now(),
        ]);

        // The view composer caches the current release; bust it so each test
        // sees the row it just created (the sync command does the same in prod).
        Cache::forget('release_current');

        return $release;
    }

    public function test_current_returns_newest_release(): void
    {
        $this->release('1.0.0');
        $newest = $this->release('1.1.0');
        $newest->forceFill(['released_at' => now()->addDay()])->save();

        $this->assertSame('1.1.0', Release::current()->version);
    }

    public function test_seen_records_version_against_user(): void
    {
        $this->release('2.0.0');
        $user = User::factory()->create(['last_seen_version' => null]);

        $this->actingAs($user)
            ->postJson(route('releases.seen'), ['version' => '2.0.0'])
            ->assertOk()
            ->assertJson(['seen' => '2.0.0']);

        $this->assertSame('2.0.0', $user->fresh()->last_seen_version);
    }

    public function test_seen_requires_a_version(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('releases.seen'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    }

    public function test_seen_requires_authentication(): void
    {
        // Web auth middleware redirects guests to login (this app reserves JSON
        // error responses for api/* — see bootstrap/app.php).
        $this->post(route('releases.seen'), ['version' => '1.0.0'])
            ->assertRedirect(route('login'));
    }

    public function test_index_renders_and_marks_latest_seen(): void
    {
        $this->release('3.1.0');
        $user = User::factory()->create(['last_seen_version' => null]);

        $this->actingAs($user)
            ->get(route('releases.index'))
            ->assertOk()
            ->assertSee('Shiny feature')
            ->assertSee('v3.1.0');

        // Visiting the feed clears the unseen badge for this user.
        $this->assertSame('3.1.0', $user->fresh()->last_seen_version);
    }

    public function test_unseen_badge_shows_until_user_sees_release(): void
    {
        $this->release('4.0.0');

        // feedback.index renders the sidebar without marking the release seen.
        $fresh = User::factory()->create(['last_seen_version' => null]);
        $this->actingAs($fresh)->get(route('feedback.index'))->assertSee('release-badge');

        $caughtUp = User::factory()->create(['last_seen_version' => '4.0.0']);
        $this->actingAs($caughtUp)->get(route('feedback.index'))->assertDontSee('release-badge');
    }

    public function test_sync_command_upserts_and_busts_cache(): void
    {
        Cache::put('release_current', 'stale', 600);
        $path = database_path('changelog.json');
        $original = file_exists($path) ? file_get_contents($path) : null;

        file_put_contents($path, json_encode([[
            'version' => '9.9.9',
            'title' => 'v9.9.9',
            'date' => now()->toIso8601String(),
            'sha' => str_repeat('b', 40),
            'notes' => ['new' => ['From changelog'], 'fixed' => [], 'improved' => []],
        ]]));

        try {
            $this->artisan('releases:sync')->assertSuccessful();

            $this->assertDatabaseHas('releases', ['version' => '9.9.9']);
            $this->assertNull(Cache::get('release_current'));
        } finally {
            // Restore the repo's real changelog so the suite stays deterministic.
            if ($original === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $original);
            }
        }
    }
}
