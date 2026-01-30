<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackFactory> */
    use HasFactory;

    public const REASON_BREAKING_MISSED = 'breaking_missed';

    public const REASON_BREAKING_FALSE_POSITIVE = 'breaking_false_positive';

    public const REASON_FEATURE_MISCATEGORIZED = 'feature_miscategorized';

    public const REASON_FIX_MISCATEGORIZED = 'fix_miscategorized';

    public const REASON_CONFIDENCE_HIGH = 'confidence_high';

    public const REASON_CONFIDENCE_LOW = 'confidence_low';

    public const REASON_OTHER = 'other';

    protected $table = 'feedback';

    protected $fillable = [
        'release_id',
        'reason_category',
        'reason_details',
        'original_version',
        'original_type',
        'user_version',
        'user_type',
        'specific_commit_sha',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Release, $this>
     */
    public function release(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return array<string, string>
     */
    public static function reasonCategories(): array
    {
        return [
            self::REASON_BREAKING_MISSED => 'Breaking change missed',
            self::REASON_BREAKING_FALSE_POSITIVE => 'Breaking change false positive',
            self::REASON_FEATURE_MISCATEGORIZED => 'Feature miscategorized as fix',
            self::REASON_FIX_MISCATEGORIZED => 'Fix miscategorized as feature',
            self::REASON_CONFIDENCE_HIGH => 'Confidence was too high',
            self::REASON_CONFIDENCE_LOW => 'Confidence was too low',
            self::REASON_OTHER => 'Other',
        ];
    }
}
