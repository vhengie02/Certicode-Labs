<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings admins change at runtime (Admin → System). Each setting has a typed
 * default here, so code can read it before an admin has ever saved anything.
 */
class SystemSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** key => [type, default] */
    public const DEFINITIONS = [
        'registration_open' => ['bool', true],
        'instructor_requests_open' => ['bool', true],
        'default_pass_mark' => ['int', 75],
        'default_lab_minutes' => ['int', 60],
        'announcement' => ['string', ''],
    ];

    private const CACHE_KEY = 'system_settings_all';

    public static function read(string $key): mixed
    {
        [$type, $default] = self::DEFINITIONS[$key] ?? ['string', null];
        $all = self::allValues();

        if (!array_key_exists($key, $all) || $all[$key] === null) {
            return $default;
        }

        return match ($type) {
            'bool' => filter_var($all[$key], FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $all[$key],
            default => (string) $all[$key],
        };
    }

    public static function write(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }
            $stored = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
            static::updateOrCreate(['key' => $key], ['value' => $stored]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** All stored values, cached briefly. Falls back to defaults if the table isn't migrated yet. */
    private static function allValues(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            return [];
        }
    }
}
