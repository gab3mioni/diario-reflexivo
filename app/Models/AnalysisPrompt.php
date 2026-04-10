<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Representa um prompt de análise utilizado para gerar análises de diário por IA.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?string $description
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnalysisPromptVersion> $versions
 * @property-read ?AnalysisPromptVersion $latestVersion
 */
class AnalysisPrompt extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'description',
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
     * Cria uma nova versão do prompt com numeração sequencial automática.
     *
     * @param  string    $content  Conteúdo do prompt.
     * @param  ?int      $userId  ID do usuário que criou a versão.
     * @return AnalysisPromptVersion
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
