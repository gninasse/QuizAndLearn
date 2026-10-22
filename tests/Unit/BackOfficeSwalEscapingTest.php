<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou XSS : les messages renvoyés par le serveur (noms de groupes, de dossiers, erreurs)
 * ne doivent jamais atteindre SweetAlert2 en HTML (title, 2e argument positionnel,
 * showValidationMessage non échappé). Pas de banc de test JS pour le back-office : on vérifie
 * statiquement les sources.
 */
class BackOfficeSwalEscapingTest extends TestCase
{
    private const FILES = [
        'public/js/modules/core/groups/contents.js',
        'public/js/admin/group-folder-picker.js',
        'public/js/admin/utils.js',
    ];

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);
    }

    public function test_toasts_use_title_text_not_html_title(): void
    {
        foreach (self::FILES as $file) {
            $js = $this->source($file);
            $this->assertDoesNotMatchRegularExpression('/\btitle:\s*message\b/', $js, "$file : title: message interprète du HTML");
            $this->assertMatchesRegularExpression('/titleText:/', $js, "$file : titleText attendu");
        }
    }

    public function test_error_dialog_does_not_use_positional_html_argument(): void
    {
        $js = $this->source('public/js/modules/core/groups/contents.js');
        $this->assertDoesNotMatchRegularExpression("/Swal\\.fire\\(\\s*'Erreur'\\s*,/", $js);
    }

    public function test_validation_messages_from_server_are_escaped(): void
    {
        foreach (['public/js/modules/core/groups/contents.js', 'public/js/admin/group-folder-picker.js'] as $file) {
            preg_match_all('/Swal\.showValidationMessage\((.*)\);/', $this->source($file), $m);
            foreach ($m[1] as $arg) {
                $literal = preg_match('/^([\'"]).*\1$/', trim($arg)) === 1;
                $escaped = preg_match('/^(esc|escapeHtml)\(/', trim($arg)) === 1;
                $this->assertTrue($literal || $escaped, "$file : showValidationMessage($arg) non échappé");
            }
        }
    }
}
