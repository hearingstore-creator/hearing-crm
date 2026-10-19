<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One small per-user setting, stored as JSON text. */
class UserPreference extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    public static function read(int $userId, string $key, $default = null)
    {
        $raw = static::where(['user_id' => $userId, 'key' => $key])->value('value');

        return $raw === null ? $default : (json_decode($raw, true) ?? $default);
    }

    public static function write(int $userId, string $key, $value): void
    {
        static::updateOrCreate(['user_id' => $userId, 'key' => $key], ['value' => json_encode($value)]);
    }
}
