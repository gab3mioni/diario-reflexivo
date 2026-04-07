<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $promptId = DB::table('analysis_prompts')->insertGetId([
            'slug' => 'diary-analysis',
            'name' => 'Análise de Diário Reflexivo',
            'description' => 'Prompt padrão para análise das respostas dos alunos no diário reflexivo.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('analysis_prompt_versions')->insert([
            'analysis_prompt_id' => $promptId,
            'version' => 1,
            'content' => <<<'PROMPT'
Você é um assistente pedagógico especializado em análise de diários reflexivos de estudantes universitários.

Analise a seguinte resposta de um aluno a um diário reflexivo sobre uma aula. A resposta é composta por perguntas do roteiro e as respostas do aluno.

Avalie os seguintes indicadores em uma escala de 1 a 5:
- compreensao: O quanto o aluno demonstra ter compreendido o conteúdo da aula (1=nenhuma compreensão, 5=compreensão profunda)
- engajamento: O nível de envolvimento e interesse demonstrado pelo aluno (1=desinteressado, 5=altamente engajado)
- pensamento_critico: A capacidade de analisar, questionar e ir além do conteúdo apresentado (1=apenas reproduz, 5=análise crítica profunda)
- clareza_expressao: A qualidade da escrita e organização das ideias (1=confuso/incoerente, 5=claro e bem estruturado)
- reflexao_pessoal: A capacidade de conectar o conteúdo com experiências e aprendizados pessoais (1=sem reflexão, 5=reflexão pessoal profunda)

Responda EXCLUSIVAMENTE com um JSON válido no seguinte formato (sem markdown, sem texto adicional):
{
  "resumo": "Resumo de 2-3 frases sobre a reflexão do aluno",
  "indicadores": {
    "compreensao": <1-5>,
    "engajamento": <1-5>,
    "pensamento_critico": <1-5>,
    "clareza_expressao": <1-5>,
    "reflexao_pessoal": <1-5>
  },
  "pontos_fortes": ["ponto forte 1", "ponto forte 2"],
  "pontos_atencao": ["ponto de atenção 1"],
  "sugestoes_acao": ["sugestão de ação para o professor 1", "sugestão 2"]
}
PROMPT,
            'created_by' => null,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('analysis_prompts')->where('slug', 'diary-analysis')->delete();
    }
};
