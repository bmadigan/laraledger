<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    /** @use HasFactory<\Database\Factories\RepositoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'github_id',
        'name',
        'full_name',
        'description',
        'default_branch',
        'tag_pattern',
        'ignored_paths',
        'settings',
        'is_private',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'ignored_paths' => 'array',
            'settings' => 'array',
            'is_private' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Release, $this>
     */
    public function releases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Tag, $this>
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tag::class)->orderByDesc('created_at_github');
    }

    public function getLatestRelease(): ?Release
    {
        return $this->releases()->latest()->first();
    }

    public function getLatestTag(): ?string
    {
        return $this->getLatestRelease()?->version;
    }
}
