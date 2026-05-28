<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registo imutável de cada alteração da versão ativa (pin/unpin) de um prompt.
 *
 * @property int $id
 * @property int $analysis_prompt_id
 * @property ?int $previous_version_id
 * @property ?int $new_version_id
 * @property ?int $actor_id
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property-read AnalysisPrompt $prompt
 * @property-read ?AnalysisPromptVersion $previousVersion
 * @property-read ?AnalysisPromptVersion $newVersion
 * @property-read ?User $actor
 */
class PromptVersionAudit extends Model
{
    /** @var bool */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'analysis_prompt_id',
        'previous_version_id',
        'new_version_id',
        'actor_id',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Prompt cuja versão ativa foi alterada.
     *
     * @return BelongsTo<AnalysisPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AnalysisPrompt::class, 'analysis_prompt_id');
    }

    /**
     * Versão que estava fixada antes desta alteração.
     *
     * @return BelongsTo<AnalysisPromptVersion, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(AnalysisPromptVersion::class, 'previous_version_id');
    }

    /**
     * Versão que passou a ficar fixada após esta alteração.
     *
     * @return BelongsTo<AnalysisPromptVersion, $this>
     */
    public function newVersion(): BelongsTo
    {
        return $this->belongsTo(AnalysisPromptVersion::class, 'new_version_id');
    }

    /**
     * Usuário que realizou a alteração.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
