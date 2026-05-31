<?php

namespace App\Console\Commands;

/**
 * Anula o raw_response de análises antigas (minimização de dados / LGPD).
 *
 * O raw_response guarda a saída bruta da IA, útil só para depuração recente.
 * Passada a janela de retenção, é anulado. Use --dry-run para conferir antes.
 */
class PurgeRawResponses extends PurgeAnalysisColumn
{
    /** @var string */
    protected $signature = 'analyses:purge-raw {--days=90 : Idade mínima da análise, em dias} {--dry-run : Apenas conta, não altera}';

    /** @var string */
    protected $description = 'Anula o raw_response de análises mais antigas que a janela de retenção.';

    protected function column(): string
    {
        return 'raw_response';
    }
}
