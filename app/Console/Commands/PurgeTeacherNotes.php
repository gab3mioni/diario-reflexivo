<?php

namespace App\Console\Commands;

/**
 * Anula o teacher_notes de análises antigas (minimização de dados / LGPD).
 *
 * O teacher_notes é texto livre do professor sobre a análise e pode conter
 * dados pessoais. Passada a janela de retenção, é anulado. Use --dry-run antes.
 */
class PurgeTeacherNotes extends PurgeAnalysisColumn
{
    /** @var string */
    protected $signature = 'analyses:purge-notes {--days=90 : Idade mínima da análise, em dias} {--dry-run : Apenas conta, não altera}';

    /** @var string */
    protected $description = 'Anula o teacher_notes de análises mais antigas que a janela de retenção.';

    protected function column(): string
    {
        return 'teacher_notes';
    }
}
