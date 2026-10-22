<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Article;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Services\ContentSharing;
use Tests\TestCase;

class FolderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mode_is_shared(): void
    {
        $this->assertSame('shared', ContentSharing::mode());
        $this->assertFalse(ContentSharing::isolated());

        ContentSharing::setMode('isolated');

        $this->assertSame('isolated', ContentSharing::mode());
        $this->assertTrue(ContentSharing::isolated());
    }

    public function test_invalid_mode_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ContentSharing::setMode('nimporte');
    }

    public function test_existing_assignment_gets_null_folder_and_zero_position(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $article = Article::create(['title' => 'T', 'content' => 'C', 'is_active' => true]);
        $group->articles()->attach($article->id);

        $row = DB::table('group_article')->first();

        $this->assertNull($row->folder_id);
        $this->assertSame(0, (int) $row->position);
        $this->assertNull($group->articles()->first()->pivot->folder_id);
    }

    public function test_deleting_folder_nulls_pivot_folder_id(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $folder = Folder::create(['group_id' => $group->id, 'name' => 'D']);
        $article = Article::create(['title' => 'T', 'content' => 'C', 'is_active' => true]);
        $group->articles()->attach($article->id, ['folder_id' => $folder->id, 'position' => 2]);

        $folder->delete();

        $pivot = $group->articles()->first()->pivot;
        $this->assertNull($pivot->folder_id);
        $this->assertSame(2, (int) $pivot->position);
    }

    public function test_descendant_ids(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $a = Folder::create(['group_id' => $group->id, 'name' => 'A']);
        $b = Folder::create(['group_id' => $group->id, 'parent_id' => $a->id, 'name' => 'B']);
        $c = Folder::create(['group_id' => $group->id, 'parent_id' => $b->id, 'name' => 'C']);
        $other = Folder::create(['group_id' => $group->id, 'name' => 'Autre']);

        $ids = $a->descendantIds();
        sort($ids);

        $this->assertSame([$a->id, $b->id, $c->id], $ids);
        $this->assertSame([$c->id], $c->descendantIds());
        $this->assertSame([$other->id], $other->descendantIds());
        $this->assertTrue($b->parent->is($a));
        $this->assertTrue($b->group->is($group));
    }

    public function test_deleting_group_cascades_folders(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $a = Folder::create(['group_id' => $group->id, 'name' => 'A']);
        Folder::create(['group_id' => $group->id, 'parent_id' => $a->id, 'name' => 'B']);

        $group->delete();

        $this->assertSame(0, Folder::count());
    }
}
