<?php

namespace Modules\Core\Support;

use Illuminate\Support\Str;
use Modules\Core\Models\User;

/**
 * Génération d'identifiants lisibles (nom d'utilisateur, mot de passe temporaire).
 */
class Credentials
{
    private const LETTERS = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const DIGITS = '23456789';

    /**
     * `prenom.nom` en ASCII minuscule, suffixé 2, 3… en cas de doublon.
     */
    public static function uniqueUserName(string $firstName, string $lastName): string
    {
        $clean = fn (string $v): string => preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($v)));

        $base = trim($clean($firstName).'.'.$clean($lastName), '.');
        if ($base === '') {
            $base = 'apprenant';
        }

        $candidate = $base;
        $i = 2;
        while (User::where('user_name', $candidate)->exists()) {
            $candidate = $base.$i++;
        }

        return $candidate;
    }

    /**
     * 10 caractères lisibles (sans 0 O 1 l I), au moins un chiffre.
     */
    public static function temporaryPassword(): string
    {
        $alphabet = self::LETTERS.self::DIGITS;
        $chars = [self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)]];

        while (count($chars) < 10) {
            $chars[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
