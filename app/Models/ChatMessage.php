<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;
    protected $fillable = [
        'lesson_response_id',
        'node_id',
        'role',
        'content',
        'classifier_status',
        'classifier_reason',
    ];

    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    /**
     * Check if this message is from the bot.
     */
    public function isBot(): bool
    {
        return $this->role === 'bot';
    }

    /**
     * Check if this message is from the student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}
