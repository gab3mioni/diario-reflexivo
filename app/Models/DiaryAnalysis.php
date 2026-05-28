<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa uma análise de diário reflexivo gerada por IA para uma resposta de aula.
 *
 * @property int $id
 * @property int $lesson_response_id
 * @property int $prompt_version_id
 * @property int $ai_provider_config_id
 * @property string $status
 * @property ?array<string, mixed> $result
 * @property ?string $raw_response
 * @property ?string $error_message
 * @property ?string $failure_reason
 * @property ?string $teacher_notes
 * @property ?int $reviewed_by
 * @property ?\Illuminate\Support\Carbon $reviewed_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read LessonResponse $lessonResponse
 * @property-read AnalysisPromptVersion $promptVersion
 * @property-read AiProviderConfig $providerConfig
 * @property-read ?User $reviewer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DiaryAnalysisAlert> $alerts
 */
class DiaryAnalysis extends Model
{
    use HasFactory;

    /** Status: aguardando processamento. */
    public const STATUS_PENDING = 'pending';

    /** Status: análise concluída com sucesso. */
    public const STATUS_COMPLETED = 'completed';

    /** Status: falha no processamento da análise. */
    public const STATUS_FAILED = 'failed';

    /** Status: análise aprovada pelo professor. */
    public const STATUS_APPROVED = 'approved';

    /** Status: análise rejeitada pelo professor. */
    public const STATUS_REJECTED = 'rejected';

    /** Falha: erro reportado pelo provedor de IA. */
    public const FAILURE_PROVIDER_ERROR = 'provider_error';

    /** Falha: provedor retornou conteúdo vazio ou não decodificável. */
    public const FAILURE_PROVIDER_EMPTY = 'provider_empty';

    /** Falha: saída não satisfaz o contrato de schema. */
    public const FAILURE_INVALID_SCHEMA = 'invalid_schema';

    /** Falha: saída estruturalmente válida mas implausível. */
    public const FAILURE_IMPLAUSIBLE = 'implausible';

    /** @var string */
    protected $table = 'diary_analyses';

    /** @var list<string> */
    protected $fillable = [
        'lesson_response_id',
        'prompt_version_id',
        'ai_provider_config_id',
        'status',
        'result',
        'raw_response',
        'error_message',
        'failure_reason',
        'teacher_notes',
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
            'result' => 'array',
            'raw_response' => 'encrypted',
            'teacher_notes' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Alertas extraídos desta análise, sempre sujeitos a revisão do professor.
     *
     * @return HasMany<DiaryAnalysisAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(DiaryAnalysisAlert::class);
    }

    /**
     * Resposta de aula analisada.
     *
     * @return BelongsTo<LessonResponse, $this>
     */
    public function lessonResponse(): BelongsTo
    {
        return $this->belongsTo(LessonResponse::class);
    }

    /**
     * Versão do prompt utilizada na análise.
     *
     * @return BelongsTo<AnalysisPromptVersion, $this>
     */
    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(AnalysisPromptVersion::class, 'prompt_version_id');
    }

    /**
     * Configuração do provedor de IA utilizada na análise.
     *
     * @return BelongsTo<AiProviderConfig, $this>
     */
    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'ai_provider_config_id');
    }

    /**
     * Professor que revisou a análise.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Verifica se a análise está pendente de processamento.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Verifica se a análise foi concluída com sucesso.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Verifica se a análise falhou.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Verifica se a análise já foi revisada (aprovada ou rejeitada).
     */
    public function isReviewed(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }
}
