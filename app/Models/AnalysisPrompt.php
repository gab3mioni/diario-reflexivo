<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Representa um prompt de análise utilizado para gerar análises de diário por IA.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?string $description
 * @property ?int $active_version_id
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnalysisPromptVersion> $versions
 * @property-read ?AnalysisPromptVersion $latestVersion
 * @property-read ?AnalysisPromptVersion $activeVersion
 */
class AnalysisPrompt extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'active_version_id',
    ];

    /**
     * Versões do prompt ordenadas da mais recente para a mais antiga.
     *
     * @return HasMany<AnalysisPromptVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AnalysisPromptVersion::class)->orderByDesc('version');
    }

    /**
     * Versão mais recente do prompt.
     *
     * @return HasOne<AnalysisPromptVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(AnalysisPromptVersion::class)->latestOfMany('version');
    }

    /**
     * Versão ativa fixada pelo admin. Quando nula, o resolvedor cai na latestVersion.
     *
     * @return BelongsTo<AnalysisPromptVersion, $this>
     */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(AnalysisPromptVersion::class, 'active_version_id');
    }

    /**
     * Resolve a versão em uso: o pin do admin se houver, caso contrário a versão mais recente.
     */
    public function resolveActiveVersion(): ?AnalysisPromptVersion
    {
        return $this->activeVersion ?? $this->latestVersion;
    }

    /**
     * Cria uma nova versão do prompt com numeração sequencial automática.
     *
     * @param  string  $content  Conteúdo do prompt.
     * @param  ?int  $userId  ID do usuário que criou a versão.
     */
    public function createVersion(string $content, ?int $userId = null): AnalysisPromptVersion
    {
        $nextVersion = ($this->versions()->max('version') ?? 0) + 1;

        return $this->versions()->create([
            'version' => $nextVersion,
            'content' => $content,
            'created_by' => $userId,
        ]);
    }
}
