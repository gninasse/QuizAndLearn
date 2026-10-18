<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\Role;
use Modules\Core\Models\Trainer;
use Modules\Core\Models\User;
use Modules\Core\Services\Kpi\KpiScope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class KpiScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $groupA;

    private Group $groupB;

    private Learner $l1;

    private Learner $l2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin');
        Role::findOrCreate('admin');
        $this->admin->assignRole('admin');

        $this->groupA = Group::create(['name' => 'A', 'is_active' => true]);
        $this->groupB = Group::create(['name' => 'B', 'is_active' => true]);
        $this->l1 = Learner::create(['user_id' => $this->makeUser('awa')->id, 'matricule' => 'M1']);
        $this->l2 = Learner::create(['user_id' => $this->makeUser('bob')->id, 'matricule' => 'M2']);
        $this->groupA->learners()->attach($this->l1->id);
        $this->groupB->learners()->attach($this->l2->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $handle): User
    {
        return User::create([
            'name' => ucfirst($handle),
            'last_name' => 'Test',
            'user_name' => $handle,
            'email' => "{$handle}@example.com",
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }

    private function makeTrainer(string $handle, array $groups = []): User
    {
        $user = $this->makeUser($handle);
        Role::findOrCreate('trainer');
        $user->assignRole('trainer');
        $trainer = Trainer::create(['user_id' => $user->id]);
        foreach ($groups as $g) {
            $g->trainers()->attach($trainer->id);
        }

        return $user->fresh();
    }

    public function test_admin_without_group_sees_all_learners(): void
    {
        $scope = KpiScope::for($this->admin);

        $this->assertNull($scope->learnerIds);
        $this->assertTrue($scope->isAdmin);
        $this->assertSame([], $scope->groupIds);
    }

    public function test_admin_with_group_is_limited_to_group_members(): void
    {
        $scope = KpiScope::for($this->admin, groupId: $this->groupA->id);

        $this->assertSame([$this->l1->id], $scope->learnerIds);
        $this->assertSame([$this->groupA->id], $scope->groupIds);
    }

    public function test_trainer_is_limited_to_own_groups(): void
    {
        $trainer = $this->makeTrainer('prof', [$this->groupA]);

        $scope = KpiScope::for($trainer);

        $this->assertFalse($scope->isAdmin);
        $this->assertSame([$this->l1->id], $scope->learnerIds);
        $this->assertSame([$this->groupA->id], $scope->groupIds);
    }

    public function test_trainer_without_groups_has_empty_scope(): void
    {
        $trainer = $this->makeTrainer('prof');

        $scope = KpiScope::for($trainer);

        $this->assertSame([], $scope->learnerIds);
        $this->assertSame([], $scope->groupIds);
    }

    public function test_trainer_foreign_group_is_forbidden(): void
    {
        $trainer = $this->makeTrainer('prof', [$this->groupA]);

        $this->expectException(AccessDeniedHttpException::class);
        KpiScope::for($trainer, groupId: $this->groupB->id);
    }

    public function test_learner_in_two_groups_is_counted_once(): void
    {
        $this->groupB->learners()->attach($this->l1->id);
        $trainer = $this->makeTrainer('prof', [$this->groupA, $this->groupB]);

        $scope = KpiScope::for($trainer);

        $this->assertCount(2, $scope->learnerIds);
        $this->assertEqualsCanonicalizing([$this->l1->id, $this->l2->id], $scope->learnerIds);
    }

    public function test_period_bounds(): void
    {
        Carbon::setTestNow('2026-10-18 15:30:00');

        $scope = KpiScope::for($this->admin, '7');

        $this->assertSame('2026-10-12 00:00:00', $scope->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-18 23:59:59', $scope->to->format('Y-m-d H:i:s'));

        $this->assertSame('2026-09-19', KpiScope::for($this->admin, '30')->from->toDateString());
        $this->assertSame('2026-07-21', KpiScope::for($this->admin, '90')->from->toDateString());
        $this->assertSame('2026-09-19', KpiScope::for($this->admin)->from->toDateString());
    }

    public function test_previous_window_has_same_length(): void
    {
        Carbon::setTestNow('2026-10-18 15:30:00');

        $prev = KpiScope::for($this->admin, '7', groupId: $this->groupA->id)->previous();

        $this->assertSame('2026-10-05 00:00:00', $prev->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-11 23:59:59', $prev->to->format('Y-m-d H:i:s'));
        $this->assertSame([$this->l1->id], $prev->learnerIds);
    }

    public function test_custom_period_validation(): void
    {
        $scope = KpiScope::for($this->admin, 'custom', '2026-01-01', '2026-01-31');
        $this->assertSame('2026-01-01 00:00:00', $scope->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 23:59:59', $scope->to->format('Y-m-d H:i:s'));

        $invalid = [
            ['custom', '2026-02-01', '2026-01-01'],
            ['custom', '2025-01-01', '2026-02-05'],
            ['custom', null, '2026-01-01'],
            ['custom', '01/01/2026', '2026-01-31'],
            ['abc', null, null],
        ];
        foreach ($invalid as [$p, $f, $t]) {
            try {
                KpiScope::for($this->admin, $p, $f, $t);
                $this->fail("Période acceptée à tort : {$p} {$f} {$t}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cache_key_differs_per_trainer_and_filters(): void
    {
        $t1 = $this->makeTrainer('prof1', [$this->groupA]);
        $t2 = $this->makeTrainer('prof2', [$this->groupA]);

        $keys = [
            KpiScope::for($t1)->cacheKey,
            KpiScope::for($t2)->cacheKey,
            KpiScope::for($this->admin)->cacheKey,
            KpiScope::for($this->admin, '7')->cacheKey,
            KpiScope::for($this->admin, groupId: $this->groupA->id)->cacheKey,
        ];

        $this->assertCount(5, array_unique($keys));
        $this->assertSame($keys[0], KpiScope::for($t1)->cacheKey);
    }

    public function test_apply_learners_filters_query(): void
    {
        $q = KpiScope::for($this->admin, groupId: $this->groupA->id)
            ->applyLearners(Learner::query(), 'id');
        $this->assertSame([$this->l1->id], $q->pluck('id')->all());

        $all = KpiScope::for($this->admin)->applyLearners(Learner::query(), 'id');
        $this->assertCount(2, $all->get());
    }
}
