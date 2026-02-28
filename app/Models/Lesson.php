<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'title',
        'description',
        'scheduled_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(LessonResponse::class);
    }

    /**
     * Get the response for a specific student.
     */
    public function responseForStudent(int $studentId): HasOne
    {
        return $this->hasOne(LessonResponse::class)->where('student_id', $studentId);
    }

    /**
     * Check if the lesson is available for student response (scheduled_at <= now).
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->scheduled_at->isPast();
    }

    /**
     * Check if the lesson is in the future (not yet available).
     */
    public function isFuture(): bool
    {
        return $this->scheduled_at->isFuture();
    }

    /**
     * Scope: only lessons that are available (scheduled_at <= now).
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('scheduled_at', '<=', now());
    }

    /**
     * Scope: only future lessons.
     */
    public function scopeFuture($query)
    {
        return $query->where('scheduled_at', '>', now());
    }
}
