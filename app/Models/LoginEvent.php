<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** One row per sign-in attempt: who, what happened, from where. Read on the user page. */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'event', 'ip', 'user_agent'];

    protected $casts = ['created_at' => 'datetime'];

    public static function record(string $event, ?string $email, ?int $userId, ?Request $request = null): void
    {
        $request ??= request();
        try {
            static::create([
                'user_id'    => $userId,
                'email'      => $email ? mb_substr($email, 0, 255) : null,
                'event'      => $event,
                'ip'         => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e); // a logging problem must never stop someone signing in
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
