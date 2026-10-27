<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    public const EVENT_LOGIN = 'login';

    public const EVENT_SESSION_REVOKED_LIMIT = 'session_revoked_limit';

    protected $fillable = [
        'user_id',
        'auth_session_id',
        'device_id',
        'ip_address',
        'user_agent',
        'event',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AuthSession::class, 'auth_session_id');
    }
}
