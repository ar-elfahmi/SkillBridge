<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Database\Factories\RpsDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property ParseStatus $parse_status
 * @property ExtractionStatus $extraction_status
 * @property ReviewStatus $review_status
 */
#[Fillable([
    'user_id', 'course_id', 'original_name', 'file_path', 'file_hash', 'file_size',
    'page_count', 'parse_status', 'extraction_status', 'review_status', 'reviewed_at', 'error_message',
])]
// Storage path is internal; never expose it in API responses.
#[Hidden(['file_path'])]
class RpsDocument extends Model
{
    /** @use HasFactory<RpsDocumentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'parse_status' => 'pending',
        'extraction_status' => 'pending',
        'review_status' => 'unreviewed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'page_count' => 'integer',
            'parse_status' => ParseStatus::class,
            'extraction_status' => ExtractionStatus::class,
            'review_status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<RpsPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(RpsPage::class)->orderBy('page_number');
    }

    /**
     * @return HasMany<CourseSkill, $this>
     */
    public function courseSkills(): HasMany
    {
        return $this->hasMany(CourseSkill::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
