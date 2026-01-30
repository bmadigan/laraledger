<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisLog extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisLogFactory> */
    use HasFactory;

    public const STAGE_FETCH_COMMITS = 'fetch_commits';

    public const STAGE_HEURISTIC_ANALYSIS = 'heuristic_analysis';

    public const STAGE_AI_CLASSIFICATION = 'ai_classification';

    public const STAGE_NOTE_GENERATION = 'note_generation';

    public const STAGE_FINALIZE = 'finalize';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'release_id',
        'stage',
        'duration_ms',
        'tokens_used',
        'model',
        'request',
        'response',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'tokens_used' => 'integer',
            'request' => 'array',
            'response' => 'array',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Release, $this>
     */
    public function release(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function wasSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }
}
