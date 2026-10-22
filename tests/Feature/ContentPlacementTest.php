<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Article;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\User;
use Modules\Core\Services\ContentPlacement;
use Tests\TestCase;

class ContentPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function learner(string $handle = 'awa'): Learner
    {
        $user = User::create([
            'name' => ucfirst($handle), 'last_name' => 'Test', 'user_name' => $handle,
            'email' => "{$handle}@example.com", 'is_active' => true, 'password' => bcrypt('password'),
        ]);

        return Learner::create(['user_id' => $user->id, 'matricule' => 'M-'.$handle]);
    }

    private function group(string $name, array $attrs = []): Group
    {
        return Group::create($attrs + ['name' => $name, 'is_active' => true]);
    }

    private function folder(Group $group, array $attrs = []): Folder
    {
        return Folder::create($attrs + ['group_id' => $group->id, 'name' => 'D', 'position' => 0, 'is_visible' => true]);
    }

    private function place(Group $group, ?Folder $folder = null): Article
    {
        $article = Article::create(['title' => 'T', 'content' => 'C', 'is_active' => true]);
        $group->articles()->attach($article->id, ['folder_id' => $folder?->id, 'position' => 3]);

        return $article;
    }

    private function service(): ContentPlacement
    {
        return new ContentPlacement;
    }

    public function test_root_placement_visible(): void
    {
        $learner = $this->learner();
        $group = $this->group('A');
        $group->learners()->attach($learner->id);
        $article = $this->place($group);

        $service = $this->service();

        $this->assertTrue($service->isVisible($learner, 'article', $article->id));
        $this->assertSame([$article->id], $service->visibleIds($learner, 'article'));
        $this->assertSame(
            [['type' => 'article', 'id' => $article->id, 'group_id' => $group->id, 'folder_id' => null, 'position' => 3]],
            $service->placementsFor($learner)->all()
        );
    }

    public function test_hidden_parent_hides_subtree(): void
    {
        $learner = $this->learner();
        $group = $this->group('A');
        $group->learners()->attach($learner->id);
        $parent = $this->folder($group, ['is_visible' => false]);
        $child = $this->folder($group, ['parent_id' => $parent->id]);
        $inChild = $this->place($group, $child);
        $inParent = $this->place($group, $parent);

        $service = $this->service();

        $this->assertFalse($service->isVisible($learner, 'article', $inChild->id));
        $this->assertFalse($service->isVisible($learner, 'article', $inParent->id));
        $this->assertSame([], $service->visibleFolderIds(collect([$group->id])));

        $parent->update(['is_visible' => true]);
        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $this->service()->visibleFolderIds(collect([$group->id])));
        $this->assertTrue($this->service()->isVisible($learner, 'article', $inChild->id));
    }

    public function test_future_available_from_hides_then_shows(): void
    {
        $learner = $this->learner();
        $group = $this->group('A');
        $group->learners()->attach($learner->id);
        $folder = $this->folder($group, ['available_from' => now()->addDays(2), 'available_until' => now()->addDays(5)]);
        $article = $this->place($group, $folder);

        $this->assertFalse($this->service()->isVisible($learner, 'article', $article->id));

        $this->travel(3)->days();
        $this->assertTrue($this->service()->isVisible($learner, 'article', $article->id));

        $this->travel(5)->days();
        $this->assertFalse($this->service()->isVisible($learner, 'article', $article->id));
    }

    public function test_hidden_in_one_group_visible_in_other(): void
    {
        $both = $this->learner('both');
        $onlyA = $this->learner('onlya');
        $a = $this->group('A');
        $b = $this->group('B');
        $a->learners()->attach([$both->id, $onlyA->id]);
        $b->learners()->attach($both->id);

        $hidden = $this->folder($a, ['is_visible' => false]);
        $article = Article::create(['title' => 'T', 'content' => 'C', 'is_active' => true]);
        $a->articles()->attach($article->id, ['folder_id' => $hidden->id, 'position' => 0]);
        $b->articles()->attach($article->id, ['folder_id' => null, 'position' => 0]);

        $this->assertTrue($this->service()->isVisible($both, 'article', $article->id));
        $this->assertFalse($this->service()->isVisible($onlyA, 'article', $article->id));
    }

    public function test_non_current_group_excluded(): void
    {
        $learner = $this->learner();
        $inactive = $this->group('Off', ['is_active' => false]);
        $ended = $this->group('Old', ['end_date' => now()->subDays(3)->toDateString()]);
        $inactive->learners()->attach($learner->id);
        $ended->learners()->attach($learner->id);
        $this->place($inactive);
        $this->place($ended);

        $this->assertSame(0, $this->service()->placementsFor($learner)->count());
    }
}
