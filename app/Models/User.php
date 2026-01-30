<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_username',
        'github_token',
        'github_refresh_token',
        'github_token_expires_at',
        'github_scopes',
        'setup_completed',
        'setup_step',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'github_token',
        'github_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'github_token_expires_at' => 'datetime',
            'github_scopes' => 'array',
            'setup_completed' => 'boolean',
            'setup_step' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Repository, $this>
     */
    public function repositories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function isGithubConnected(): bool
    {
        return $this->github_id !== null && $this->github_token !== null;
    }

    public function hasCompletedSetup(): bool
    {
        return $this->setup_completed;
    }
}
