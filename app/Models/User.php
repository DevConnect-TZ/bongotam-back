<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'unlocked_connection_videos', 'unlocked_wakubwa_videos', 'wakubwa_subscription_expires_at', 'last_login'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory\u003cUserFactory\u003e */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array\u003cstring, string\u003e
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'unlocked_connection_videos' => 'array',
            'unlocked_wakubwa_videos' => 'array',
            'wakubwa_subscription_expires_at' => 'datetime',
            'last_login' => 'datetime',
        ];
    }

    /**
     * Check if the user has an active Wakubwa Zone subscription.
     */
    public function isWakubwaSubscribed(): bool
    {
        if (! $this->wakubwa_subscription_expires_at) {
            return false;
        }

        return $this->wakubwa_subscription_expires_at->isFuture();
    }

    /**
     * Subscribe user to Wakubwa Zone for a given number of months.
     */
    public function subscribeToWakubwa(int $months = 1): void
    {
        $now = now();

        // If there's an active subscription, extend from its end date.
        // Otherwise, start from now.
        $start = $this->isWakubwaSubscribed()
            ? $this->wakubwa_subscription_expires_at
            : $now;

        $this->update([
            'wakubwa_subscription_expires_at' => $start->copy()->addMonths($months),
        ]);
    }
}
