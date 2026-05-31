<?php

namespace App\Console\Commands;

use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use App\Models\PromptVersionAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fixa (promove) uma versão de prompt como ativa pela linha de comando.
 *
 * Espelha a promoção da tela de admin: atualiza active_version_id e grava a
 * trilha em prompt_version_audits na mesma transação. Origem CLI fica com
 * actor_id nulo.
 */
class PromoteAnalysisPrompt extends Command
{
    /** @var string */
    protected $signature = 'prompt:promote {slug : Slug do prompt} {version : Número da versão}';

    /** @var string */
    protected $description = 'Promove uma versão de prompt para ativa, com trilha de auditoria.';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');

        $prompt = AnalysisPrompt::where('slug', $slug)->first();

        if (! $prompt) {
            $this->error("Prompt '{$slug}' não encontrado.");

            return self::FAILURE;
        }

        $versionNumber = (int) $this->argument('version');

        $version = AnalysisPromptVersion::where('analysis_prompt_id', $prompt->id)
            ->where('version', $versionNumber)
            ->first();

        if (! $version) {
            $this->error("Versão {$versionNumber} não existe para '{$slug}'.");

            return self::FAILURE;
        }

        if ($prompt->active_version_id === $version->id) {
            $this->info("Versão {$versionNumber} já está ativa. Nada a fazer.");

            return self::SUCCESS;
        }

        $previousVersionId = $prompt->active_version_id;

        DB::transaction(function () use ($prompt, $version, $previousVersionId) {
            $prompt->update(['active_version_id' => $version->id]);

            PromptVersionAudit::create([
                'analysis_prompt_id' => $prompt->id,
                'previous_version_id' => $previousVersionId,
                'new_version_id' => $version->id,
                'actor_id' => null,
            ]);
        });

        $this->info("Versão {$versionNumber} de '{$slug}' promovida para ativa.");

        return self::SUCCESS;
    }
}
