<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\Group;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

class AccessRequestAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Boss', 'last_name' => 'Test', 'user_name' => 'boss',
            'email' => 'boss@example.com', 'is_active' => true, 'password' => bcrypt('password'),
        ]);
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        return $user;
    }

    private function makeRequest(array $override = []): AccessRequest
    {
        return AccessRequest::create(array_merge([
            'first_name' => 'Awa', 'last_name' => 'Kone', 'phone' => '+2250701020304',
            'whatsapp' => '+2250708080808', 'status' => 'new',
            'consent_at' => now(), 'consent_text' => 'OK',
        ], $override));
    }

    private function payload(array $override = []): array
    {
        return array_merge(['name' => 'Awa', 'last_name' => 'Kone', 'phone' => '07 01 02 03 04'], $override);
    }

    public function test_creates_learner_prefilled_from_request_with_group(): void
    {
        $group = Group::create(['name' => 'Promo A', 'is_active' => true]);
        $req = $this->makeRequest(['group_id' => $group->id]);

        $res = $this->actingAs($this->admin())->postJson(
            route('cores.access-requests.account', $req->id),
            $this->payload(['group_id' => $group->id, 'matricule' => 'M-1'])
        );

        $res->assertCreated()->assertJsonPath('success', true)->assertJsonPath('login', '+2250701020304');
        $req->refresh();
        $this->assertSame('account_created', $req->status);
        $this->assertNotNull($req->learner_id);
        $this->assertTrue($req->learner->groups->contains($group->id));
        $text = $res->json('whatsapp_text');
        $this->assertStringContainsString('Bonjour Awa, votre accès '.config('branding.name').' est prêt.', $text);
        $this->assertStringContainsString('Mot de passe provisoire : '.$res->json('temporary_password'), $text);
        $this->assertStringStartsWith('https://wa.me/2250708080808?text=', $res->json('whatsapp_url'));
    }

    public function test_account_can_be_created_without_payment(): void
    {
        $req = $this->makeRequest();
        $this->assertSame(0, $req->payments()->count());

        $this->actingAs($this->admin())
            ->postJson(route('cores.access-requests.account', $req->id), $this->payload())
            ->assertCreated();
    }

    public function test_duplicate_phone_returns_422_with_existing_account_hint(): void
    {
        User::create([
            'name' => 'Fatou', 'last_name' => 'Diallo', 'user_name' => 'fd',
            'phone' => '+2250701020304', 'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $req = $this->makeRequest();

        $this->actingAs($this->admin())
            ->postJson(route('cores.access-requests.account', $req->id), $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Ce numéro de téléphone est déjà utilisé par Fatou Diallo.');
        $this->assertSame('new', $req->fresh()->status);
    }

    public function test_temporary_password_is_returned_once_and_forces_change(): void
    {
        $req = $this->makeRequest();
        $admin = $this->admin();

        $res = $this->actingAs($admin)->postJson(route('cores.access-requests.account', $req->id), $this->payload());
        $password = $res->json('temporary_password');
        $user = User::where('phone', '+2250701020304')->first();

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue((bool) $user->must_change_password);

        $detail = $this->actingAs($admin)->getJson(route('cores.access-requests.show', $req->id));
        $this->assertStringNotContainsString($password, $detail->getContent());

        $this->actingAs($admin)->postJson(route('cores.access-requests.account', $req->id), $this->payload())->assertStatus(422);
    }

    public function test_generic_status_endpoint_rejects_account_created(): void
    {
        $req = $this->makeRequest();

        $this->actingAs($this->admin())
            ->postJson(route('cores.access-requests.status', $req->id), ['status' => 'account_created'])
            ->assertStatus(422);
        $this->assertSame('new', $req->fresh()->status);
    }

    public function test_purge_deletes_closed_requests_older_than_retention(): void
    {
        $old = now()->subMonths(13);
        $oldRejected = $this->makeRequest(['status' => 'rejected']);
        $oldAbandoned = $this->makeRequest(['status' => 'abandoned']);
        $oldOpen = $this->makeRequest(['status' => 'new']);
        $recentRejected = $this->makeRequest(['status' => 'rejected']);
        foreach ([$oldRejected, $oldAbandoned, $oldOpen] as $r) {
            $r->timestamps = false;
            $r->forceFill(['updated_at' => $old])->save();
        }

        Artisan::call('showcase:purge-requests');

        $this->assertNull(AccessRequest::find($oldRejected->id));
        $this->assertNull(AccessRequest::find($oldAbandoned->id));
        $this->assertNotNull(AccessRequest::find($oldOpen->id));
        $this->assertNotNull(AccessRequest::find($recentRejected->id));
    }
}
