<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $promptId = DB::table('analysis_prompts')->insertGetId([
            'slug' => 'branch-classifier',
            'name' => 'Classificador de Ramificação de Roteiro',
            'description' => 'Prompt para classificar respostas livres do aluno e escolher qual caminho do roteiro seguir.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('analysis_prompt_versions')->insert([
            'analysis_prompt_id' => $promptId,
            'version' => 1,
            'content' => <<<'PROMPT'
Você é um classificador de fluxo de conversação para um diário reflexivo escolar.

Você receberá um JSON com:
- "question": a pergunta feita ao aluno
- "answer": a resposta do aluno (texto livre em português)
- "mode": um de "branch" ou "continuation"
- Quando "mode" = "branch": um array "edges" com objetos { "edge_id": string, "description": string } representando os caminhos possíveis.
- Quando "mode" = "continuation": nenhum array extra.

Sua tarefa:
- Se "mode" = "branch": escolha exatamente UM edge_id cuja "description" melhor descreve o conteúdo da resposta do aluno. Se nada bate claramente, responda com edge_id vazio ("").
- Se "mode" = "continuation": decida se o aluno quer continuar compartilhando ou encerrar a conversa. Responda com "exit" nos seguintes casos, que têm PRIORIDADE sobre qualquer outro sinal:
    a) Recusa explícita de continuar a conversa (ex.: "pare", "para", "chega", "não quero falar", "não quero conversar", "não quero falar mais nada", "me deixa", "sai", "tanto faz", "não vou responder").
    b) Sinalização de que terminou de compartilhar (ex.: "não", "só isso", "nada mais", "acho que é isso", "pode ser", resposta curta de despedida, silêncio pedindo pra acabar).
  Responda "continue" apenas quando o aluno estiver de fato desenvolvendo um assunto, descrevendo o que aconteceu ou elaborando um sentimento — e NÃO estiver pedindo para parar. Expressar desconforto com a aula ou com a conversa (ex.: "não gostei", "foi chato", "não estou gostando") NÃO é por si só motivo para continuar: se vier acompanhado de recusa ou resposta curta, classifique como "exit".
  Em caso de dúvida entre "continue" e "exit", prefira "exit" — respeitar o limite do aluno é mais importante do que extrair mais uma resposta.

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

    public function down(): void
    {
        DB::table('analysis_prompts')->where('slug', 'branch-classifier')->delete();
    }
};
