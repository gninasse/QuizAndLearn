<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages clé/valeur de l'application, mis en cache et invalidés à l'écriture.
 */
class AppSetting extends Model
{
    private const CACHE_KEY = 'app_settings.all';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function allSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable((new self)->getTable())) {
                return [];
            }

            return self::query()->get()->mapWithKeys(fn (self $s) => [$s->key => $s->value])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allSettings();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }
}
