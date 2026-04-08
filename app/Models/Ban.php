<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ban extends Model
{
    protected $fillable = ['ip_address', 'reason', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Check if a given IP is currently banned.
     */
    public static function isBanned(string $ip): bool
    {
        return static::where('ip_address', $ip)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get how many minutes are remaining on a ban for an IP.
     */
    public static function minutesRemaining(string $ip): int
    {
        $ban = static::where('ip_address', $ip)
            ->where('expires_at', '>', now())
            ->first();

        return $ban ? (int) now()->diffInMinutes($ban->expires_at) + 1 : 0;
    }

    /**
     * Ban an IP address.
     */
    public static function banIp(string $ip, string $reason = 'kata_kasar'): void
    {
        // Remove old bans for this IP first
        static::where('ip_address', $ip)->delete();

        static::create([
            'ip_address' => $ip,
            'reason'     => $reason,
            'expires_at' => now()->addHour(),
        ]);
    }
}
