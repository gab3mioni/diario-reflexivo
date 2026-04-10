<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa um alerta gerado para uma resposta de aula que requer atenção do professor.
 *
 * @property int $id
 * @property int $lesson_response_id
 * @property string $type
 * @property string $severity
 * @property string $reason
 * @property ?\Illuminate\Support\Carbon $read_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read LessonResponse $lessonResponse
 */
class ResponseAlert extends Model
{
    use HasFactory;

    /** Tipo: ausência do aluno. */
    public const TYPE_ABSENCE = 'absence';

    /** Tipo: limite de turnos de conversa atingido. */
    public const TYPE_TURN_CAP = 'turn_cap_reached';

    /** Tipo: falha no classificador de ramificação. */
    public const TYPE_CLASSIFIER_FAILURE = 'classifier_failure';

    /** Tipo: sinal de risco identificado na resposta. */
    public const TYPE_RISK_SIGNAL = 'risk_signal';

    /** Severidade: baixa. */
    public const SEVERITY_LOW = 'low';

    /** Severidade: média. */
    public const SEVERITY_MEDIUM = 'medium';

    /** Severidade: alta. */
    public const SEVERITY_HIGH = 'high';

    /** @var list<string> */
    protected $fillable = [
        'lesson_response_id',
        'type',
        'severity',
        'reason',
        'read_at',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * Resposta de aula associada ao alerta.
     *
     * @return BelongsTo<LessonResponse, $this>
     */
    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    /**
     * Scope: apenas alertas não lidos.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Marca o alerta como lido com o timestamp atual.
     *
     * @return void
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
