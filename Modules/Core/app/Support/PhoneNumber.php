<?php

namespace Modules\Core\Support;

/**
 * Normalisation des numéros de téléphone au format E.164.
 */
class PhoneNumber
{
    /**
     * Renvoie le numéro au format E.164 (`+` et 8 à 15 chiffres) ou null s'il est invalide.
     */
    public static function normalize(?string $raw, ?string $defaultCountryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        $international = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if (! $international && str_starts_with($digits, '00')) {
            $international = true;
            $digits = substr($digits, 2);
        }

        if (! $international) {
            $code = preg_replace('/\D+/', '', (string) ($defaultCountryCode ?? config('showcase.default_country_code', '225')));

            // Déjà préfixé par l'indicatif par défaut (ex. 2250700000000).
            if ($code !== '' && str_starts_with($digits, $code) && strlen($digits) >= strlen($code) + 8) {
                // numéro complet avec indicatif
            } else {
                $digits = $code.$digits;
            }
        }

        $length = strlen($digits);
        if ($length < 8 || $length > 15 || $digits[0] === '0') {
            return null;
        }

        return '+'.$digits;
    }
}
