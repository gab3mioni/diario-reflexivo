<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'student_id',
        'content',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function diaryAnalyses(): HasMany
    {
        return $this->hasMany(DiaryAnalysis::class)->orderByDesc('created_at');
    }

    public function latestAnalysis(): ?DiaryAnalysis
    {
        return $this->diaryAnalyses()->first();
    }
}
