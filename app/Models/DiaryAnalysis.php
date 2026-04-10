<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryAnalysis extends Model
{
    use HasFactory;

    protected $table = 'diary_analyses';

    protected $fillable = [
        'lesson_response_id',
        'prompt_version_id',
        'ai_provider_config_id',
        'status',
        'result',
        'raw_response',
        'error_message',
        'teacher_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(AnalysisPromptVersion::class, 'prompt_version_id');
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'ai_provider_config_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isReviewed(): bool
    {
        return in_array($this->status, ['approved', 'rejected']);
    }
}
