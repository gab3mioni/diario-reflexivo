<?php

namespace App\Console\Commands;

use App\Models\DiaryAnalysis;
use Illuminate\Console\Command;

/**
 * Anula o raw_response de análises antigas (minimização de dados / LGPD).
 *
 * O raw_response guarda a saída bruta da IA, útil só para depuração recente.
 * Passada a janela de retenção, é anulado em lotes. Comando manual/agendável;
 * use --dry-run para conferir o alcance antes.
 */
class PurgeRawResponses extends Command
{
    /** @var string */
    protected $signature = 'analyses:purge-raw {--days=90 : Idade mínima da análise, em dias} {--dry-run : Apenas conta, não altera}';

    /** @var string */
    protected $description = 'Anula o raw_response de análises mais antigas que a janela de retenção.';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $base = DiaryAnalysis::whereNotNull('raw_response')->where('created_at', '<', $cutoff);

        if ($dryRun) {
            $this->info("[dry-run] {$base->count()} análise(s) com raw_response anteriores a {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $purged = 0;

        $base->chunkById(500, function ($analyses) use (&$purged) {
            $purged += DiaryAnalysis::whereKey($analyses->modelKeys())->update(['raw_response' => null]);
        });

        $this->info("{$purged} raw_response anulado(s).");

        return self::SUCCESS;
    }
}
