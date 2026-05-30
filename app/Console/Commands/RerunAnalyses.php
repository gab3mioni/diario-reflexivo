<?php

namespace App\Console\Commands;

use App\Exceptions\AiProviderException;
use App\Models\DiaryAnalysis;
use App\Models\LessonResponse;
use App\Services\DiaryAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Redispara análises de IA para um conjunto de respostas já submetidas.
 *
 * Exige um escopo (--lesson, --response ou --failed) para nunca reanalisar
 * tudo por engano — cada análise custa uma chamada de IA. Respostas com
 * análise pendente são puladas (sem duplicar). Use --dry-run para conferir.
 */
class RerunAnalyses extends Command
{
    /** @var string */
    protected $signature = 'analyses:rerun
        {--lesson= : Reanalisa as respostas submetidas desta aula}
        {--response= : Reanalisa apenas esta resposta}
        {--failed : Reanalisa respostas cuja análise falhou}
        {--dry-run : Apenas lista, não dispara}';

    /** @var string */
    protected $description = 'Redispara análises de IA para um escopo de respostas submetidas.';

    public function handle(DiaryAnalysisService $service): int
    {
        $responses = $this->resolveResponses();

        if ($responses === null) {
            $this->error('Informe um escopo: --lesson=ID, --response=ID ou --failed.');

            return self::FAILURE;
        }

        $eligible = $responses->filter(
            fn (LessonResponse $r) => ! $this->hasPendingAnalysis($r->id) && $service->canRequestAnalysis($r->id)
        );

        if ($eligible->isEmpty()) {
            $this->info('Nenhuma resposta elegível (todas sem submissão, já com análise pendente ou no limite de tentativas).');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info("[dry-run] {$eligible->count()} resposta(s) seriam reanalisadas.");

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($eligible as $response) {
            try {
                $service->requestAnalysis($response);
                $dispatched++;
            } catch (AiProviderException $e) {
                $this->error("Abortado: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info("{$dispatched} análise(s) redisparada(s).");

        return self::SUCCESS;
    }

    /**
     * Indica se a resposta já tem uma análise pendente em andamento.
     *
     * Evita enfileirar uma segunda análise para a mesma resposta enquanto a
     * primeira não terminou.
     */
    private function hasPendingAnalysis(int $lessonResponseId): bool
    {
        return DiaryAnalysis::where('lesson_response_id', $lessonResponseId)
            ->where('status', DiaryAnalysis::STATUS_PENDING)
            ->exists();
    }

    /**
     * Resolve as respostas alvo conforme o escopo, ou null se nenhum foi dado.
     *
     * @return ?Collection<int, LessonResponse>
     */
    private function resolveResponses(): ?Collection
    {
        if ($responseId = $this->option('response')) {
            return LessonResponse::whereKey($responseId)
                ->whereNotNull('submitted_at')
                ->get();
        }

        if ($lessonId = $this->option('lesson')) {
            return LessonResponse::where('lesson_id', $lessonId)
                ->whereNotNull('submitted_at')
                ->get();
        }

        if ((bool) $this->option('failed')) {
            $responseIds = DiaryAnalysis::where('status', DiaryAnalysis::STATUS_FAILED)
                ->distinct()
                ->pluck('lesson_response_id');

            return LessonResponse::whereKey($responseIds)
                ->whereNotNull('submitted_at')
                ->get();
        }

        return null;
    }
}
