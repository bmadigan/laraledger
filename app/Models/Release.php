<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    /** @use HasFactory<\Database\Factories\ReleaseFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_MAJOR = 'MAJOR';

    public const TYPE_MINOR = 'MINOR';

    public const TYPE_PATCH = 'PATCH';

    public const SOURCE_HEURISTIC = 'heuristic';

    public const SOURCE_AI = 'ai';

    public const SOURCE_COMBINED = 'combined';

    protected $appends = ['reasoning'];

    protected $fillable = [
        'repository_id',
        'version',
        'from_ref',
        'to_ref',
        'recommended_version',
        'recommended_type',
        'final_version',
        'final_type',
        'confidence',
        'status',
        'release_notes',
        'commits',
        'changes',
        'heuristic_reasoning',
        'ai_reasoning',
        'analysis_source',
        'cost_usd',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'commits' => 'array',
            'changes' => 'array',
            'heuristic_reasoning' => 'array',
            'ai_reasoning' => 'array',
            'cost_usd' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Repository, $this>
     */
    public function repository(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<Feedback, $this>
     */
    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AnalysisLog, $this>
     */
    public function analysisLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnalysisLog::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isAdjusted(): bool
    {
        return $this->status === self::STATUS_ADJUSTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function wasCorrect(): bool
    {
        return $this->isAccepted() || ($this->isAdjusted() && $this->recommended_type === $this->final_type);
    }

    /**
     * @return array<int, string>
     */
    public function getBreakingChanges(): array
    {
        $changes = $this->getAttribute('changes');

        return $changes['breaking'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getFeatures(): array
    {
        $changes = $this->getAttribute('changes');

        return $changes['features'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getFixes(): array
    {
        $changes = $this->getAttribute('changes');

        return $changes['fixes'] ?? [];
    }

    /**
     * Get combined reasoning from heuristic and AI analysis.
     *
     * @return array<int, string>
     */
    public function getReasoningAttribute(): array
    {
        $reasoning = [];

        if ($this->heuristic_reasoning) {
            $reasoning = array_merge($reasoning, (array) $this->heuristic_reasoning);
        }

        if ($this->ai_reasoning) {
            $reasoning = array_merge($reasoning, (array) $this->ai_reasoning);
        }

        return $reasoning;
    }
}
