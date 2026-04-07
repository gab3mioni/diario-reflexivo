<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisPrompt extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(AnalysisPromptVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AnalysisPromptVersion::class)->latestOfMany('version');
    }

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
