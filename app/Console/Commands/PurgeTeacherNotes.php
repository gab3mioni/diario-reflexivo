<?php

namespace App\Console\Commands;

use App\Models\DiaryAnalysis;
use Illuminate\Console\Command;

/**
 * Anula o teacher_notes de análises antigas (minimização de dados / LGPD).
 *
 * O teacher_notes é texto livre do professor sobre a análise e pode conter
 * dados pessoais. Passada a janela de retenção, é anulado em lotes. Comando
 * manual/agendável; use --dry-run para conferir o alcance antes.
 */
class PurgeTeacherNotes extends Command
{
    /** @var string */
    protected $signature = 'analyses:purge-notes {--days=90 : Idade mínima da análise, em dias} {--dry-run : Apenas conta, não altera}';

    /** @var string */
    protected $description = 'Anula o teacher_notes de análises mais antigas que a janela de retenção.';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $base = DiaryAnalysis::whereNotNull('teacher_notes')->where('created_at', '<', $cutoff);

        if ($dryRun) {
            $this->info("[dry-run] {$base->count()} análise(s) com teacher_notes anteriores a {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $purged = 0;

        $base->chunkById(500, function ($analyses) use (&$purged) {
            $purged += DiaryAnalysis::whereKey($analyses->modelKeys())->update(['teacher_notes' => null]);
        });

        $this->info("{$purged} teacher_notes anulado(s).");

        return self::SUCCESS;
    }
}
