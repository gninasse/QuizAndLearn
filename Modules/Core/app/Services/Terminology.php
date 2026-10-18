<?php

namespace Modules\Core\Services;

use Modules\Core\Models\AppSetting;

/**
 * Libellé configurable du terme « groupe » (Classe, Promotion, Cohorte…).
 * Stocké dans `app_settings` sous la clé `terminology.group`.
 */
class Terminology
{
    public const SETTING_KEY = 'terminology.group';

    /** @var array<string, array{singular: string, plural: string, feminine: bool}> */
    public const PRESETS = [
        'groupe' => ['singular' => 'groupe', 'plural' => 'groupes', 'feminine' => false],
        'classe' => ['singular' => 'classe', 'plural' => 'classes', 'feminine' => true],
        'promotion' => ['singular' => 'promotion', 'plural' => 'promotions', 'feminine' => true],
        'cohorte' => ['singular' => 'cohorte', 'plural' => 'cohortes', 'feminine' => true],
        'session' => ['singular' => 'session', 'plural' => 'sessions', 'feminine' => true],
    ];

    public const KEYS = ['group', 'groups', 'Group', 'Groups', 'a_group', 'the_group', 'of_group', 'to_group', 'this_group', 'no_group'];

    /**
     * @return array{preset: string, singular: string, plural: string, feminine: bool}
     */
    public function config(): array
    {
        $stored = AppSetting::get(self::SETTING_KEY);
        $stored = is_array($stored) ? $stored : [];
        $preset = (string) ($stored['preset'] ?? 'groupe');

        if ($preset === 'custom') {
            $singular = trim((string) ($stored['singular'] ?? ''));
            $plural = trim((string) ($stored['plural'] ?? ''));
            if ($singular !== '' && $plural !== '') {
                return ['preset' => 'custom', 'singular' => $singular, 'plural' => $plural, 'feminine' => (bool) ($stored['feminine'] ?? false)];
            }
            $preset = 'groupe';
        }

        return ['preset' => $preset] + (self::PRESETS[$preset] ?? self::PRESETS['groupe']);
    }

    public function label(string $key): string
    {
        $c = $this->config();
        $s = $c['singular'];
        $fem = $c['feminine'];
        $vowel = preg_match('/^[aeiouyhàâäéèêëîïôöûùü]/iu', $s) === 1;

        return match ($key) {
            'group' => $s,
            'groups' => $c['plural'],
            'Group' => $this->ucfirst($s),
            'Groups' => $this->ucfirst($c['plural']),
            'a_group' => ($fem ? 'une ' : 'un ').$s,
            'the_group' => $vowel ? "l'".$s : ($fem ? 'la ' : 'le ').$s,
            'of_group' => $vowel ? "de l'".$s : ($fem ? 'de la ' : 'du ').$s,
            'to_group' => $vowel ? "à l'".$s : ($fem ? 'à la ' : 'au ').$s,
            'this_group' => $fem ? 'cette '.$s : ($vowel ? 'cet ' : 'ce ').$s,
            'no_group' => ($fem ? 'aucune ' : 'aucun ').$s,
            'fem_e' => $fem ? 'e' : '', // accord des adjectifs : « assigné{e}s » (usage Blade uniquement)
            default => $key,
        };
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = $this->label($key);
        }

        return $out;
    }

    /**
     * @param  array{preset: string, singular?: ?string, plural?: ?string, feminine?: mixed}  $validated
     */
    public function save(array $validated): void
    {
        $preset = $validated['preset'];
        if ($preset === 'custom') {
            $value = [
                'preset' => 'custom',
                'singular' => trim((string) $validated['singular']),
                'plural' => trim((string) $validated['plural']),
                'feminine' => (bool) ($validated['feminine'] ?? false),
            ];
        } else {
            $value = ['preset' => $preset] + self::PRESETS[$preset];
        }
        AppSetting::put(self::SETTING_KEY, $value);
    }

    private function ucfirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
