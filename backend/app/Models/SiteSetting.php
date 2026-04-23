<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getAll(): array
    {
        return static::pluck('value', 'key')->toArray();
    }

    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value);
        }
    }
}
