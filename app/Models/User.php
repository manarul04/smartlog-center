<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Administrator',
        'analyst' => 'Security Analyst',
        'viewer' => 'Viewer',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'theme',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageUsers(): bool
    {
        return $this->role === 'admin';
    }

    public function canSaveViews(): bool
    {
        return in_array($this->role, ['admin', 'analyst'], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    /**
     * What the UI shows for an account: the username when present, otherwise
     * the legacy email.
     */
    public function handle(): string
    {
        return (string) ($this->username ?: $this->email);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name));
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== '') {
                $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            }
        }

        return $letters !== '' ? $letters : 'U';
    }
}
