<?php

namespace Database\Factories;

use App\Models\AiProviderConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiProviderConfigFactory extends Factory
{
    protected $model = AiProviderConfig::class;

    public function definition(): array
    {
        return [
            'slug' => 'provider-'.$this->faker->unique()->slug(2),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'api_key' => 'test-key',
            'base_url' => null,
            'is_active' => false,
        ];
    }
}
