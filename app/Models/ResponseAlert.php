<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseAlert extends Model
{
    public const TYPE_ABSENCE = 'absence';
    public const TYPE_TURN_CAP = 'turn_cap_reached';
    public const TYPE_CLASSIFIER_FAILURE = 'classifier_failure';
    public const TYPE_RISK_SIGNAL = 'risk_signal';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';

    protected $fillable = [
        'lesson_response_id',
        'type',
        'severity',
        'reason',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
