<?php

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Jeton invité du mode découverte : simple date d'expiration chiffrée,
 * valable 24 h, renouvelée à chaque appel de /api/showcase/v1/content.
 */
class GuestToken
{
    public const COOKIE = 'lq_guest';

    public const TTL_HOURS = 24;

    public static function issue(): string
    {
        return Crypt::encryptString(json_encode([
            'exp' => now()->addHours(self::TTL_HOURS)->getTimestamp(),
        ]));
    }

    public static function valid(?string $token): bool
    {
        return self::expiresAt($token) !== null;
    }

    /**
     * Date d'expiration d'un jeton valide et non expiré, null sinon.
     */
    public static function expiresAt(?string $token): ?CarbonInterface
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        $exp = is_array($payload) ? ($payload['exp'] ?? null) : null;
        if (! is_int($exp) || $exp <= now()->getTimestamp()) {
            return null;
        }

        return Carbon::createFromTimestamp($exp);
    }
}
