<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les routes des éditeurs vivent sous /cores/editor/... : les scripts lisent leurs URL
 * dans les attributs data-url-* posés par Blade (route()), jamais un chemin écrit en dur.
 */
class BackOfficeEditorUrlsTest extends TestCase
{
    public function test_editor_scripts_have_no_hardcoded_admin_paths(): void
    {
        $root = dirname(__DIR__, 2).'/public/js/admin';
        $files = array_merge(glob($root.'/*.js'), glob($root.'/modals/*.js'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                "#['\"`]/admin/#",
                (string) file_get_contents($file),
                basename($file).' : chemin /admin/... en dur (route inexistante)'
            );
        }
    }
}
