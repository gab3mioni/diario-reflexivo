<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuração de um provedor de IA utilizado para análises de diário.
 *
 * @property int $id
 * @property string $slug
 * @property string $provider
 * @property string $model
 * @property float $temperature
 * @property string $api_key
 * @property ?string $base_url
 * @property bool $is_active
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DiaryAnalysis> $diaryAnalyses
 */
class AiProviderConfig extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'provider',
        'model',
        'temperature',
        'api_key',
        'base_url',
        'is_active',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Retorna a configuração de provedor atualmente ativa.
     *
     * @return ?static
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Análises de diário processadas com esta configuração de provedor.
     *
     * @return HasMany<DiaryAnalysis, $this>
     */
    public function diaryAnalyses(): HasMany
    {
        return $this->hasMany(DiaryAnalysis::class);
    }
}
