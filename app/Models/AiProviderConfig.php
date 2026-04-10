<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProviderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'provider',
        'model',
        'temperature',
        'api_key',
        'base_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function diaryAnalyses(): HasMany
    {
        return $this->hasMany(DiaryAnalysis::class);
    }
}
