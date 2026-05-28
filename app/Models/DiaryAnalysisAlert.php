<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta socioemocional ou pedagógico extraído de uma análise de diário.
 *
 * Cada alerta é sempre human-gated: nasce com status pendente e só produz ação
 * após revisão do professor. Nunca dispara automação a partir do estado da IA.
 *
 * @property int $id
 * @property int $diary_analysis_id
 * @property int $lesson_response_id
 * @property string $type
 * @property string $severity
 * @property string $title
 * @property ?string $detail
 * @property ?string $evidence
 * @property ?int $confidence
 * @property string $status
 * @property ?string $teacher_note
 * @property ?int $reviewed_by
 * @property ?\Illuminate\Support\Carbon $reviewed_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read DiaryAnalysis $analysis
 * @property-read LessonResponse $lessonResponse
 * @property-read ?User $reviewer
 */
class DiaryAnalysisAlert extends Model
{
    use HasFactory;

    /** Severidade: informativo. */
    public const SEVERITY_INFO = 'info';

    /** Severidade: atenção. */
    public const SEVERITY_WARNING = 'warning';

    /** Severidade: crítico, requer atenção imediata do professor. */
    public const SEVERITY_CRITICAL = 'critical';

    /** Status: aguardando revisão do professor. */
    public const STATUS_PENDING = 'pending';

    /** Status: professor reconheceu o alerta como pertinente. */
    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    /** Status: professor descartou o alerta. */
    public const STATUS_DISMISSED = 'dismissed';

    /**
     * Severidades aceitas, em ordem crescente de gravidade.
     *
     * @var list<string>
     */
    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_CRITICAL,
    ];

    /**
     * Status de revisão aceitos.
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_DISMISSED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'diary_analysis_id',
        'lesson_response_id',
        'type',
        'severity',
        'title',
        'detail',
        'evidence',
        'confidence',
        'status',
        'teacher_note',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Análise de diário que originou o alerta.
     *
     * @return BelongsTo<DiaryAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(DiaryAnalysis::class, 'diary_analysis_id');
    }

    /**
     * Resposta de aula à qual o alerta se refere.
     *
     * @return BelongsTo<LessonResponse, $this>
     */
    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class, 'lesson_response_id');
    }

    /**
     * Professor que revisou o alerta.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
