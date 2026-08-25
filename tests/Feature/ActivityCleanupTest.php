<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Activity;
use Tests\TestCase;

class ActivityCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function makeActivity(array $attributes): Activity
    {
        return Activity::create(array_merge([
            'log_name' => 'default',
            'description' => 'updated',
            'module' => 'core',
        ], $attributes));
    }

    public function test_expired_scope_only_matches_past_expirations(): void
    {
        $this->makeActivity(['expires_at' => now()->subDay()]);
        $this->makeActivity(['expires_at' => now()->addDay()]);
        $this->makeActivity(['expires_at' => null]);

        $this->assertSame(1, Activity::expired()->count());
    }

    public function test_cleanup_command_deletes_expired_non_critical_activities(): void
    {
        $expired = $this->makeActivity(['expires_at' => now()->subDay()]);
        $critical = $this->makeActivity(['description' => 'deleted', 'expires_at' => now()->subDay()]);
        $fresh = $this->makeActivity(['expires_at' => now()->addMonth()]);

        $this->artisan('activities:cleanup-expired', ['--force' => false])
            ->expectsConfirmation('Êtes-vous sûr de vouloir supprimer 1 activité(s) expirée(s) ?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $expired->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $critical->id]); // critique conservée
        $this->assertDatabaseHas('activity_log', ['id' => $fresh->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $expired = $this->makeActivity(['expires_at' => now()->subDay()]);

        $this->artisan('activities:cleanup-expired', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['id' => $expired->id]);
    }
}
