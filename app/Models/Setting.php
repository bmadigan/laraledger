<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->is_encrypted && $setting->value) {
            return decrypt($setting->value);
        }

        return $setting->value;
    }

    public static function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $data = [
            'value' => $encrypt && $value ? encrypt($value) : $value,
            'is_encrypted' => $encrypt,
        ];

        static::updateOrCreate(['key' => $key], $data);
    }

    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();
    }

    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }
}
