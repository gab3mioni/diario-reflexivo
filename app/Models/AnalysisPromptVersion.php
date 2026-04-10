<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa uma versão imutável de um prompt de análise.
 *
 * @property int $id
 * @property int $analysis_prompt_id
 * @property int $version
 * @property string $content
 * @property ?int $created_by
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property-read AnalysisPrompt $prompt
 * @property-read ?User $creator
 */
class AnalysisPromptVersion extends Model
{
    use HasFactory;

    /** @var bool */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'analysis_prompt_id',
        'version',
        'content',
        'created_by',
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
     * Prompt de análise ao qual esta versão pertence.
     *
     * @return BelongsTo<AnalysisPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AnalysisPrompt::class, 'analysis_prompt_id');
    }

    /**
     * Usuário que criou esta versão do prompt.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
