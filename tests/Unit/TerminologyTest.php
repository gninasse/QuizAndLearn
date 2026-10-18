<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\Terminology;
use Tests\TestCase;

class TerminologyTest extends TestCase
{
    use RefreshDatabase;

    private function use(array $v): Terminology
    {
        $t = new Terminology;
        $t->save($v);

        return $t;
    }

    public function test_default_is_groupe(): void
    {
        $t = new Terminology;
        $this->assertSame('groupe', $t->label('group'));
        $this->assertSame('Groupes', $t->label('Groups'));
        $this->assertSame('un groupe', $t->label('a_group'));
        $this->assertSame('le groupe', $t->label('the_group'));
        $this->assertSame('du groupe', $t->label('of_group'));
        $this->assertSame('ce groupe', $t->label('this_group'));
        $this->assertSame('aucun groupe', $t->label('no_group'));
    }

    public function test_classe_feminine(): void
    {
        $t = $this->use(['preset' => 'classe']);
        $this->assertSame('classe', $t->label('group'));
        $this->assertSame('classes', $t->label('groups'));
        $this->assertSame('Classe', $t->label('Group'));
        $this->assertSame('Classes', $t->label('Groups'));
        $this->assertSame('une classe', $t->label('a_group'));
        $this->assertSame('la classe', $t->label('the_group'));
        $this->assertSame('de la classe', $t->label('of_group'));
        $this->assertSame('cette classe', $t->label('this_group'));
        $this->assertSame('aucune classe', $t->label('no_group'));
    }

    public function test_other_presets(): void
    {
        foreach (['promotion', 'cohorte', 'session'] as $p) {
            $t = $this->use(['preset' => $p]);
            $this->assertSame($p, $t->label('group'));
            $this->assertSame("une $p", $t->label('a_group'));
        }
    }

    public function test_custom_elision_masculine_vowel(): void
    {
        $t = $this->use(['preset' => 'custom', 'singular' => 'atelier', 'plural' => 'ateliers', 'feminine' => false]);
        $this->assertSame("l'atelier", $t->label('the_group'));
        $this->assertSame("de l'atelier", $t->label('of_group'));
        $this->assertSame('cet atelier', $t->label('this_group'));
        $this->assertSame('un atelier', $t->label('a_group'));
    }

    public function test_custom_elision_feminine_and_accent(): void
    {
        $t = $this->use(['preset' => 'custom', 'singular' => 'équipe', 'plural' => 'équipes', 'feminine' => true]);
        $this->assertSame("l'équipe", $t->label('the_group'));
        $this->assertSame("de l'équipe", $t->label('of_group'));
        $this->assertSame('cette équipe', $t->label('this_group'));
        $this->assertSame('Équipes', $t->label('Groups'));
    }

    public function test_to_array_has_all_keys(): void
    {
        $arr = (new Terminology)->toArray();
        $this->assertSame(Terminology::KEYS, array_keys($arr));
        $this->assertSame('groupes', $arr['groups']);
        $this->assertSame('groupes', term('groups'));
    }

    public function test_to_group_and_fem_e(): void
    {
        $t = new Terminology;
        $this->assertSame('au groupe', $t->label('to_group'));
        $this->assertSame('', $t->label('fem_e'));
        $t->save(['preset' => 'classe']);
        $this->assertSame('à la classe', $t->label('to_group'));
        $this->assertSame('e', $t->label('fem_e'));
        $t->save(['preset' => 'custom', 'singular' => 'équipe', 'plural' => 'équipes', 'feminine' => true]);
        $this->assertSame("à l'équipe", $t->label('to_group'));
        $t->save(['preset' => 'custom', 'singular' => 'atelier', 'plural' => 'ateliers', 'feminine' => false]);
        $this->assertSame("à l'atelier", $t->label('to_group'));
    }
}
