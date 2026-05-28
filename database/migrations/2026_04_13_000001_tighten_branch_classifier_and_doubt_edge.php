<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $promptId = DB::table('analysis_prompts')->where('slug', 'branch-classifier')->value('id');

        if ($promptId) {
            $nextVersion = ((int) DB::table('analysis_prompt_versions')
                ->where('analysis_prompt_id', $promptId)
                ->max('version')) + 1;

            DB::table('analysis_prompt_versions')->insert([
                'analysis_prompt_id' => $promptId,
                'version' => $nextVersion,
                'content' => <<<'PROMPT'
Você é um classificador de fluxo de conversação para um diário reflexivo escolar.

Você receberá um JSON com:
- "question": a pergunta feita ao aluno
- "answer": a resposta do aluno (texto livre em português)
- "mode": um de "branch" ou "continuation"
- Quando "mode" = "branch": um array "edges" com objetos { "edge_id": string, "description": string } representando os caminhos CONDICIONAIS possíveis. Existe SEMPRE um caminho padrão (default) que NÃO aparece nesse array — ele é seguido quando nenhum edge condicional se aplica.
- Quando "mode" = "continuation": nenhum array extra.

## Regra central (mode = branch)

O array "edges" contém apenas caminhos CONDICIONAIS. Escolher um edge é uma AFIRMAÇÃO FORTE de que a resposta do aluno satisfaz explicitamente aquela condição. O padrão é NÃO escolher nenhum edge.

- Só escolha um edge_id se houver evidência CLARA, EXPLÍCITA e DIRETA na resposta do aluno de que a condição descrita ocorre. Pistas vagas, menção ao tema, tom reflexivo ou hesitação NÃO bastam.
- Se houver qualquer ambiguidade, se a resposta for neutra, curta, genérica ou apenas descritiva, responda com edge_id vazio ("").
- Mesmo quando houver apenas UM edge candidato no array, NÃO o escolha por padrão — a presença de um único candidato não enfraquece o critério. Continue exigindo evidência explícita.
- Preferir "" a escolher errado. Errar escolhendo um edge muda o rumo da conversa de forma visível para o aluno; errar retornando "" apenas mantém o fluxo padrão.

### Exemplos (ilustrativos, independentes do conteúdo específico do edge)

Pergunta: "O que foi mais marcante pra você nessa aula? Algo chamou sua atenção ou gerou alguma dúvida?"
Edge candidato: "O aluno expressa explicitamente que não entendeu algo, faz uma pergunta sobre o conteúdo ou afirma ter ficado confuso."

- answer: "Gostei da parte das frações, foi legal ver como dividir coisas" → edge_id: "" (resposta positiva, sem dúvida)
- answer: "Foi ok, nada demais" → edge_id: "" (neutra)
- answer: "Achei difícil" → edge_id: "" (dificuldade genérica, sem afirmação explícita de não ter entendido)
- answer: "Não entendi como funciona a multiplicação de frações, fiquei perdido" → edge_id do edge candidato (afirmação explícita de não-compreensão)
- answer: "Por que a gente inverte a segunda fração?" → edge_id do edge candidato (pergunta direta sobre o conteúdo)
- answer: "Foi legal mas fiquei com uma dúvida na parte do final" → edge_id do edge candidato (dúvida explícita)
- answer: "Gostei bastante, o professor explicou bem" → edge_id: "" (sem sinal da condição)

## Regra para mode = continuation

Decida se o aluno quer continuar compartilhando ou encerrar a conversa. Responda com "exit" nos seguintes casos, que têm PRIORIDADE sobre qualquer outro sinal:
a) Recusa explícita de continuar a conversa (ex.: "pare", "para", "chega", "não quero falar", "não quero conversar", "não quero falar mais nada", "me deixa", "sai", "tanto faz", "não vou responder").
b) Sinalização de que terminou de compartilhar (ex.: "não", "só isso", "nada mais", "acho que é isso", "pode ser", resposta curta de despedida, silêncio pedindo pra acabar).

Responda "continue" apenas quando o aluno estiver de fato desenvolvendo um assunto, descrevendo o que aconteceu ou elaborando um sentimento — e NÃO estiver pedindo para parar. Expressar desconforto com a aula ou com a conversa (ex.: "não gostei", "foi chato", "não estou gostando") NÃO é por si só motivo para continuar: se vier acompanhado de recusa ou resposta curta, classifique como "exit".

Em caso de dúvida entre "continue" e "exit", prefira "exit" — respeitar o limite do aluno é mais importante do que extrair mais uma resposta.

## Formato de resposta

Responda EXCLUSIVAMENTE com um JSON válido, sem markdown, no seguinte formato:

Para mode=branch:
{ "edge_id": "<id_escolhido_ou_vazio>" }

Para mode=continuation:
{ "decision": "continue" | "exit" }
PROMPT,
                'created_by' => null,
                'created_at' => now(),
            ]);
        }

        $newDescription = 'O aluno expressa explicitamente que NÃO entendeu algo da aula, faz uma pergunta direta sobre o conteúdo ou afirma ter ficado confuso com um tópico específico. Não basta mencionar o tema, usar tom reflexivo ou dizer que foi "difícil" de forma genérica.';

        $scripts = DB::table('question_scripts')->get(['id', 'edges']);

        foreach ($scripts as $script) {
            $edges = json_decode($script->edges, true);
            if (! is_array($edges)) {
                continue;
            }

            $changed = false;
            foreach ($edges as &$edge) {
                if (($edge['id'] ?? null) === 'e-learning-doubt') {
                    $edge['condition']['description'] = $newDescription;
                    $changed = true;
                }
            }
            unset($edge);

            if ($changed) {
                DB::table('question_scripts')
                    ->where('id', $script->id)
                    ->update([
                        'edges' => json_encode($edges, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        $promptId = DB::table('analysis_prompts')->where('slug', 'branch-classifier')->value('id');

        if ($promptId) {
            $latest = DB::table('analysis_prompt_versions')
                ->where('analysis_prompt_id', $promptId)
                ->max('version');

            if ($latest && $latest > 1) {
                DB::table('analysis_prompt_versions')
                    ->where('analysis_prompt_id', $promptId)
                    ->where('version', $latest)
                    ->delete();
            }
        }

        $oldDescription = 'O aluno demonstra dúvida, dificuldade de compreensão ou confusão sobre o conteúdo da aula.';

        $scripts = DB::table('question_scripts')->get(['id', 'edges']);

        foreach ($scripts as $script) {
            $edges = json_decode($script->edges, true);
            if (! is_array($edges)) {
                continue;
            }

            $changed = false;
            foreach ($edges as &$edge) {
                if (($edge['id'] ?? null) === 'e-learning-doubt') {
                    $edge['condition']['description'] = $oldDescription;
                    $changed = true;
                }
            }
            unset($edge);

            if ($changed) {
                DB::table('question_scripts')
                    ->where('id', $script->id)
                    ->update([
                        'edges' => json_encode($edges, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
