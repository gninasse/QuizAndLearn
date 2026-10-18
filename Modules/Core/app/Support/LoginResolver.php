<?php

namespace Modules\Core\Support;

use Modules\Core\Models\User;

/**
 * Retrouve un utilisateur à partir d'un identifiant de connexion
 * (e-mail, téléphone ou nom d'utilisateur).
 */
class LoginResolver
{
    public static function find(string $login): ?User
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return User::whereRaw('LOWER(email) = ?', [mb_strtolower($login)])->first();
        }

        if (preg_match('/^[+\d\s\-.()]+$/', $login) && preg_match_all('/\d/', $login) >= 8) {
            $phone = PhoneNumber::normalize($login);

            return $phone ? User::where('phone', $phone)->first() : null;
        }

        return User::where('user_name', $login)->first();
    }
}
