<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

class AccessRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $handle, string $role): User
    {
        $user = User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
        Role::findOrCreate($role);
        $user->assignRole($role);

        return $user;
    }

    private function makeRequest(array $override = []): AccessRequest
    {
        return AccessRequest::create(array_merge([
            'first_name' => 'Awa',
            'last_name' => 'Kone',
            'phone' => '+2250701020304',
            'status' => 'new',
            'consent_at' => now(),
            'consent_text' => 'OK',
        ], $override));
    }

    private function payment(array $override = []): array
    {
        return array_merge([
            'amount' => 5000,
            'operator' => 'wave',
            'reference' => 'TX123',
            'paid_at' => now()->toDateString(),
            'payer_phone' => '07 01 02 03 04',
        ], $override);
    }

    public function test_admin_lists_and_filters_requests(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $this->makeRequest();
        $this->makeRequest(['first_name' => 'Moussa', 'last_name' => 'Traore', 'phone' => '+2250505050505', 'status' => 'contacted']);

        $this->actingAs($admin)->get(route('cores.access-requests.index'))->assertOk();

        $this->actingAs($admin)->getJson(route('cores.access-requests.data'))
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('counts.contacted', 1)
            ->assertJsonPath('counts.paid', 0);

        $this->actingAs($admin)->getJson(route('cores.access-requests.data', ['status' => 'contacted']))
            ->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.first_name', 'Moussa');

        $this->actingAs($admin)->getJson(route('cores.access-requests.data', ['q' => '0701']))
            ->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.first_name', 'Awa');
    }

    public function test_trainer_is_forbidden(): void
    {
        $trainer = $this->makeUser('coach', 'trainer');
        $r = $this->makeRequest();
        $this->actingAs($trainer);

        $this->get(route('cores.access-requests.index'))->assertForbidden();
        $this->getJson(route('cores.access-requests.data'))->assertForbidden();
        $this->getJson(route('cores.access-requests.show', $r->id))->assertForbidden();
        $this->postJson(route('cores.access-requests.status', $r->id), ['status' => 'contacted'])->assertForbidden();
        $this->putJson(route('cores.access-requests.notes', $r->id), ['notes' => 'x'])->assertForbidden();
        $this->postJson(route('cores.access-requests.payments', $r->id), $this->payment())->assertForbidden();
        $this->deleteJson(route('cores.access-requests.payments.destroy', [$r->id, 1]))->assertForbidden();
    }

    public function test_status_change_is_journaled(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $r = $this->makeRequest();

        $this->actingAs($admin)->postJson(route('cores.access-requests.status', $r->id), ['status' => 'contacted', 'comment' => 'Appelé'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame('contacted', $r->fresh()->status);
        $this->assertDatabaseHas('access_request_events', [
            'access_request_id' => $r->id, 'from_status' => 'new', 'to_status' => 'contacted',
            'comment' => 'Appelé', 'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->postJson(route('cores.access-requests.status', $r->id), ['status' => 'bogus'])
            ->assertStatus(422);

        $this->actingAs($admin)->putJson(route('cores.access-requests.notes', $r->id), ['notes' => 'Rappeler demain'])->assertOk();
        $this->assertSame('Rappeler demain', $r->fresh()->notes);
    }

    public function test_recording_payment_moves_to_paid_and_keeps_history(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $r = $this->makeRequest(['status' => 'payment_pending']);

        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment())
            ->assertCreated()->assertJsonPath('success', true);

        $this->assertSame('paid', $r->fresh()->status);
        $this->assertDatabaseHas('access_request_payments', [
            'access_request_id' => $r->id, 'amount' => 5000, 'currency' => 'XOF',
            'operator' => 'wave', 'payer_phone' => '+2250701020304', 'recorded_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('access_request_events', [
            'access_request_id' => $r->id, 'from_status' => 'payment_pending', 'to_status' => 'paid',
        ]);
    }

    public function test_multiple_payments_are_allowed(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $r = $this->makeRequest();

        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment())->assertCreated();
        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment(['reference' => 'TX124']))->assertCreated();

        $this->assertSame(2, $r->payments()->count());
        $this->assertSame('paid', $r->fresh()->status);

        $payment = $r->payments()->first();
        $this->actingAs($admin)->deleteJson(route('cores.access-requests.payments.destroy', [$r->id, $payment->id]))->assertOk();
        $this->assertSame(1, $r->payments()->count());
    }

    public function test_invalid_operator_is_rejected(): void
    {
        $admin = $this->makeUser('boss', 'admin');
        $r = $this->makeRequest();

        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment(['operator' => 'paypal']))
            ->assertStatus(422);
        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment(['amount' => 0]))
            ->assertStatus(422);
        $this->actingAs($admin)->postJson(route('cores.access-requests.payments', $r->id), $this->payment(['reference' => '']))
            ->assertStatus(422);

        $this->assertSame(0, $r->payments()->count());
        $this->assertSame('new', $r->fresh()->status);
    }
}
