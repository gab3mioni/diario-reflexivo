<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $promptId = DB::table('analysis_prompts')->where('slug', 'branch-classifier')->value('id');

        if (! $promptId) {
            return;
        }

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
- "answer": a resposta atual do aluno (texto livre em português)
- "mode": um de "branch", "continuation" ou "engagement"
- Quando "mode" = "branch": um array "edges" com objetos { "edge_id": string, "description": string } representando os caminhos CONDICIONAIS possíveis. Existe SEMPRE um caminho padrão (default) que NÃO aparece nesse array — ele é seguido quando nenhum edge condicional se aplica.
- Quando "mode" = "continuation": nenhum array extra.
- Quando "mode" = "engagement": um array opcional "recent_turns" com até 3 respostas anteriores do aluno no MESMO espaço de conversa livre, da mais antiga para a mais recente. Pode estar ausente ou vazio.

## Regra para mode = branch

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

## Regra para mode = engagement

Você está avaliando o estado de engajamento do aluno em um espaço de conversa livre. Use "answer" (resposta atual) sempre. Quando "recent_turns" estiver preenchido, leve em conta a TENDÊNCIA — uma resposta curta isolada após respostas elaboradas não é desengajamento. Se "recent_turns" estiver vazio ou ausente, avalie só pela resposta atual.

### engagement_level — escolha um dos três:

- "high": resposta elaborada, com detalhes, conexões, sentimentos próprios, exemplos, ou reflexão sobre o conteúdo/experiência.
- "medium": resposta funcional — o aluno responde a pergunta mas sem muita extensão ou profundidade.
- "low": resposta curta, mecânica, ruidosa ("ok", "tá", "sla", "nada", "tanto faz"), evasiva, ou que demonstra desinteresse claro. Também: respostas que pedem para encerrar ou recusam continuar.

### decision — escolha uma das quatro, aplicando as regras na ordem abaixo:

1) **"exit"** — quando houver RECUSA EXPLÍCITA de continuar (ex.: "pare", "para", "chega", "não quero falar", "me deixa", "tanto faz", "não vou responder"). PRIORIDADE absoluta sobre as demais. Engagement_level normalmente será "low" nesses casos.

2) **"ask_to_end"** — quando o engajamento está baixo de forma PERSISTENTE: todas (ou quase todas) as respostas em "recent_turns" são curtas/ruidosas/desinteressadas E "answer" também é, SEM recusa explícita. Sugere ao condutor perguntar ao aluno se quer encerrar.

3) **"reengage"** — engagement_level "low" na resposta atual, mas SEM persistência (recent_turns ausente, vazio, ou misto). Sinaliza que vale uma tentativa de destravar a conversa com mais empatia ou outro ângulo. NÃO use quando há recusa explícita.

4) **"continue"** — quando o aluno está engajado (high ou medium), OU quando há sinal claro de desenvolvimento ainda que com hesitação. Caso padrão para qualquer dúvida positiva.

### rationale

Uma frase curta (até 120 caracteres) em PT-BR explicando a decisão.

### Exemplos

- answer: "Foi muito legal! Aprendi sobre frações e gostei de pensar em como dividir as coisas em partes." | recent_turns: [] → { "engagement_level": "high", "decision": "continue", "rationale": "Resposta elaborada, com reflexão própria." }
- answer: "Foi ok." | recent_turns: [] → { "engagement_level": "low", "decision": "reengage", "rationale": "Resposta curta sem histórico; vale destravar." }
- answer: "sla" | recent_turns: ["nada", "ok"] → { "engagement_level": "low", "decision": "ask_to_end", "rationale": "Três respostas seguidas vazias indicam desengajamento persistente." }
- answer: "não quero falar disso" | recent_turns: ["..."] → { "engagement_level": "low", "decision": "exit", "rationale": "Recusa explícita do aluno." }
- answer: "achei difícil entender, fiquei meio perdida" | recent_turns: ["ok"] → { "engagement_level": "medium", "decision": "continue", "rationale": "Aluna está abrindo sobre dificuldade, sinal de retomada." }
- answer: "queria entender melhor mas o exemplo do triângulo me confundiu" | recent_turns: [] → { "engagement_level": "high", "decision": "continue", "rationale": "Detalhe específico, mostra reflexão sobre o conteúdo." }

## Formato de resposta

Responda EXCLUSIVAMENTE com um JSON válido, sem markdown, no formato correspondente ao mode recebido:

Para mode=branch:
{ "edge_id": "<id_escolhido_ou_vazio>" }

Para mode=continuation:
{ "decision": "continue" | "exit" }

Para mode=engagement:
{ "engagement_level": "high" | "medium" | "low", "decision": "continue" | "reengage" | "ask_to_end" | "exit", "rationale": "..." }
PROMPT,
            'created_by' => null,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        $promptId = DB::table('analysis_prompts')->where('slug', 'branch-classifier')->value('id');

        if (! $promptId) {
            return;
        }

        $latest = DB::table('analysis_prompt_versions')
            ->where('analysis_prompt_id', $promptId)
            ->max('version');

        if ($latest && $latest > 1) {
            DB::table('analysis_prompt_versions')
                ->where('analysis_prompt_id', $promptId)
                ->where('version', $latest)
                ->delete();
        }

        // Limpa active_version_id se apontava para a versão removida.
        DB::table('analysis_prompts')
            ->where('id', $promptId)
            ->whereNotExists(function ($q) use ($promptId) {
                $q->select(DB::raw(1))
                    ->from('analysis_prompt_versions')
                    ->whereColumn('analysis_prompt_versions.id', 'analysis_prompts.active_version_id')
                    ->where('analysis_prompts.id', $promptId);
            })
            ->update(['active_version_id' => null]);
    }
};
