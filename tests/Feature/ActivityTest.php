<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Activity;
use Modules\Core\Models\User;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur et l'authentifier
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_access_activities_index()
    {
        $response = $this->get(route('cores.activities.index'));

        $response->assertStatus(200);
        $response->assertViewIs('core::activities.index');
    }

    public function test_can_get_activities_data()
    {
        // Créer quelques activités
        activity()->log('test log');

        $response = $this->get(route('cores.activities.data'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total',
            'rows',
        ]);
    }

    public function test_can_view_activity_detail()
    {
        $activity = activity()->log('detail test');

        $response = $this->get(route('cores.activities.show', ['id' => $activity->id]));

        $response->assertStatus(200);
        $response->assertViewIs('core::activities.show');
        $response->assertSee('detail test');
    }

    public function test_can_filter_by_log_name()
    {
        activity('custom_log')->log('log message');

        $response = $this->get(route('cores.activities.data', ['log_name' => 'custom_log']));

        $response->assertStatus(200);
        $this->assertEquals(1, collect($response->json('rows'))->where('log_name', 'custom_log')->count());
    }

    public function test_can_filter_by_ip_address()
    {
        activity()->tap(function ($activity) {
            $activity->ip_address = '1.2.3.4';
        })->log('ip test');

        $response = $this->get(route('cores.activities.data', ['ip_address' => '1.2.3.4']));

        $response->assertStatus(200);
        $this->assertNotEmpty(collect($response->json('rows')));
    }

    public function test_can_filter_by_causer_type_system()
    {
        // Activity with no causer
        activity()->log('system message');

        $response = $this->get(route('cores.activities.data', ['causer_type' => 'system']));

        $response->assertStatus(200);
        $this->assertEquals('Système', $response->json('rows.0.causer_name'));
    }
}
