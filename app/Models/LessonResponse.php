<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa a resposta de um aluno a uma aula, incluindo o estado do chat reflexivo.
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $student_id
 * @property ?string $content
 * @property ?\Illuminate\Support\Carbon $submitted_at
 * @property int $student_message_count
 * @property int $free_talk_turn_count
 * @property bool $awaiting_final_check
 * @property string $chat_state
 * @property ?\Illuminate\Support\Carbon $chat_state_since
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read Lesson $lesson
 * @property-read User $student
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $chatMessages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DiaryAnalysis> $diaryAnalyses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResponseAlert> $alerts
 */
class LessonResponse extends Model
{
    use HasFactory;

    /** Estado do chat: ocioso, aguardando interação do aluno. */
    public const CHAT_STATE_IDLE = 'idle';

    /** Estado do chat: processando resposta da IA. */
    public const CHAT_STATE_PROCESSING = 'processing';

    /** @var list<string> */
    protected $fillable = [
        'lesson_id',
        'student_id',
        'content',
        'submitted_at',
        'student_message_count',
        'free_talk_turn_count',
        'awaiting_final_check',
        'chat_state',
        'chat_state_since',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'awaiting_final_check' => 'boolean',
            'student_message_count' => 'integer',
            'free_talk_turn_count' => 'integer',
            'chat_state_since' => 'datetime',
        ];
    }

    /**
     * Verifica se o chat está em processamento pela IA.
     *
     * @return bool
     */
    public function isChatProcessing(): bool
    {
        return $this->chat_state === self::CHAT_STATE_PROCESSING;
    }

    /**
     * Alertas gerados para esta resposta.
     *
     * @return HasMany<ResponseAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(ResponseAlert::class)->orderByDesc('created_at');
    }

    /**
     * Aula à qual esta resposta pertence.
     *
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Aluno que submeteu esta resposta.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Mensagens do chat reflexivo ordenadas cronologicamente.
     *
     * @return HasMany<ChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    /**
     * Análises de diário geradas por IA, da mais recente para a mais antiga.
     *
     * @return HasMany<DiaryAnalysis, $this>
     */
    public function diaryAnalyses(): HasMany
    {
        return $this->hasMany(DiaryAnalysis::class)->orderByDesc('created_at');
    }

    /**
     * Retorna a análise de diário mais recente.
     *
     * @return ?DiaryAnalysis
     */
    public function latestAnalysis(): ?DiaryAnalysis
    {
        return $this->diaryAnalyses()->first();
    }
}
