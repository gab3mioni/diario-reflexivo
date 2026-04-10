<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa uma mensagem individual no chat reflexivo entre aluno e bot.
 *
 * @property int $id
 * @property int $lesson_response_id
 * @property ?string $node_id
 * @property string $role
 * @property string $content
 * @property ?string $classifier_status
 * @property ?string $classifier_reason
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read LessonResponse $lessonResponse
 */
class ChatMessage extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'lesson_response_id',
        'node_id',
        'role',
        'content',
        'classifier_status',
        'classifier_reason',
    ];

    /**
     * Resposta de aula à qual esta mensagem pertence.
     *
     * @return BelongsTo<LessonResponse, $this>
     */
    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    /**
     * Verifica se a mensagem foi enviada pelo bot.
     *
     * @return bool
     */
    public function isBot(): bool
    {
        return $this->role === 'bot';
    }

    /**
     * Verifica se a mensagem foi enviada pelo aluno.
     *
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}
