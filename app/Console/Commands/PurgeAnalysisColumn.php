<?php

namespace App\Console\Commands;

use App\Models\DiaryAnalysis;
use Illuminate\Console\Command;

/**
 * Base para comandos que anulam uma coluna sensível de análises antigas.
 *
 * Minimização de dados (LGPD): passada a janela de retenção, a coluna alvo é
 * anulada com um UPDATE set-based (sem hidratar modelos). Cada comando concreto
 * define apenas a coluna; assinatura e descrição ficam em cada um.
 */
abstract class PurgeAnalysisColumn extends Command
{
    /**
     * Coluna a ser anulada (ex.: 'raw_response', 'teacher_notes').
     */
    abstract protected function column(): string;

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $column = $this->column();

        $base = DiaryAnalysis::whereNotNull($column)->where('created_at', '<', $cutoff);

        if ((bool) $this->option('dry-run')) {
            $this->info("[dry-run] {$base->count()} análise(s) com {$column} anteriores a {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $purged = $base->update([$column => null]);

        $this->info("{$purged} {$column} anulado(s).");

        return self::SUCCESS;
    }
}
