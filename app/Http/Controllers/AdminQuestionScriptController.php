<?php

namespace App\Http\Controllers;

use App\Models\QuestionScript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

/**
 * Controlador de gerenciamento de roteiros de perguntas para administradores.
 */
class AdminQuestionScriptController extends Controller
{
    /**
     * Lista todos os roteiros de perguntas cadastrados.
     *
     * @return \Inertia\Response
     */
    public function index(): InertiaResponse
    {
        $this->authorize('viewAny', QuestionScript::class);

        $scripts = QuestionScript::orderBy('created_at', 'desc')
            ->get()
            ->map(fn(QuestionScript $script) => [
                'id' => $script->id,
                'name' => $script->name,
                'description' => $script->description,
                'is_active' => $script->is_active,
                'questions_count' => collect($script->nodes)->where('type', 'question')->count(),
                'created_at' => $script->created_at->toISOString(),
                'updated_at' => $script->updated_at->toISOString(),
            ]);

        return inertia('admin/question-scripts/index', [
            'scripts' => $scripts,
        ]);
    }

    /**
     * Exibe um roteiro de perguntas específico com sua estrutura completa.
     *
     * @param  \App\Models\QuestionScript  $questionScript
     * @return \Inertia\Response
     */
    public function show(QuestionScript $questionScript): InertiaResponse
    {
        $this->authorize('view', $questionScript);

        $orderedNodes = $questionScript->getOrderedNodes();

        return inertia('admin/question-scripts/show', [
            'script' => [
                'id' => $questionScript->id,
                'name' => $questionScript->name,
                'description' => $questionScript->description,
                'is_active' => $questionScript->is_active,
                'nodes' => $questionScript->nodes,
                'edges' => $questionScript->edges,
                'ordered_nodes' => $orderedNodes,
                'created_at' => $questionScript->created_at->toISOString(),
                'updated_at' => $questionScript->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Atualiza um roteiro de perguntas (nós, conexões e metadados).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\QuestionScript  $questionScript
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, QuestionScript $questionScript): RedirectResponse
    {
        $this->authorize('update', $questionScript);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'nodes' => 'required|array|min:1',
            'nodes.*.id' => 'required|string',
            'nodes.*.type' => 'required|string|in:start,question,free_talk,end',
            'nodes.*.position' => 'required|array',
            'nodes.*.position.x' => 'required|numeric',
            'nodes.*.position.y' => 'required|numeric',
            'nodes.*.data.message' => 'required|string',
            'nodes.*.data.collection_type' => 'nullable|string|in:option,free_text',
            'nodes.*.data.options' => 'nullable|array',
            'nodes.*.data.options.*.label' => 'required_with:nodes.*.data.options|string|max:120',
            'nodes.*.data.max_turns' => 'nullable|integer|min:1|max:6',
            'nodes.*.data.closing_message' => 'nullable|string|max:1000',
            'nodes.*.data.alert' => 'nullable|array',
            'nodes.*.data.alert.type' => 'required_with:nodes.*.data.alert|string|in:absence,risk_signal',
            'nodes.*.data.alert.severity' => 'required_with:nodes.*.data.alert|string|in:low,medium,high',
            'nodes.*.data.alert.reason' => 'nullable|string|max:500',
            'edges' => 'required|array',
            'edges.*.id' => 'required|string',
            'edges.*.source' => 'required|string',
            'edges.*.target' => 'required|string',
            'edges.*.is_default' => 'nullable|boolean',
            'edges.*.condition' => 'nullable|array',
            'edges.*.condition.description' => 'nullable|string|max:500',
        ]);

        $this->validateGraphSemantics($validated['nodes'], $validated['edges']);

        $questionScript->update($validated);

        return redirect()->route('question-scripts.show', $questionScript->id)
            ->with('success', 'Roteiro atualizado com sucesso!');
    }

    /**
     * Valida as invariantes estruturais do grafo que a validação do Laravel
     * não consegue expressar: exatamente uma aresta padrão em cada bifurcação,
     * todo caminho alcança um nó final, nós de pergunta declaram collection_type
     * e nós de opção possuem arestas correspondentes.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateGraphSemantics(array $nodes, array $edges): void
    {
        $errors = [];

        $nodesById = [];
        foreach ($nodes as $node) {
            $nodesById[$node['id']] = $node;
        }

        $startNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? null) === 'start');
        $endNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? null) === 'end');
        if (count($startNodes) !== 1) {
            $errors['nodes'] = 'O roteiro precisa ter exatamente um nó inicial.';
        }
        if (count($endNodes) < 1) {
            $errors['nodes'] = 'O roteiro precisa ter pelo menos um nó final.';
        }

        $outgoingBySource = [];
        foreach ($edges as $edge) {
            $outgoingBySource[$edge['source']][] = $edge;
            if (! isset($nodesById[$edge['source']]) || ! isset($nodesById[$edge['target']])) {
                $errors['edges'] = 'Uma conexão aponta pra um nó inexistente.';
            }
        }

        foreach ($outgoingBySource as $sourceId => $sourceEdges) {
            $sourceNode = $nodesById[$sourceId] ?? null;
            if (! $sourceNode) {
                continue;
            }
            if (($sourceNode['type'] ?? null) === 'end') {
                $errors['edges'] = 'Nós finais não podem ter saídas.';
                continue;
            }
            if (count($sourceEdges) > 1) {
                $defaults = array_filter($sourceEdges, fn ($e) => ! empty($e['is_default']));
                if (count($defaults) !== 1) {
                    $errors['edges'] = "O nó {$sourceId} precisa ter exatamente uma conexão padrão.";
                }
            }

            if (($sourceNode['type'] ?? null) === 'question'
                && ($sourceNode['data']['collection_type'] ?? null) === 'option'
                && count($sourceEdges) > 1
            ) {
                $optionLabels = array_map(
                    fn ($o) => mb_strtolower(trim($o['label'] ?? '')),
                    $sourceNode['data']['options'] ?? [],
                );
                foreach ($sourceEdges as $edge) {
                    if (! empty($edge['is_default'])) {
                        continue;
                    }
                    $label = mb_strtolower(trim($edge['condition']['description'] ?? ''));
                    if (! in_array($label, $optionLabels, true)) {
                        $errors['edges'] = "A conexão {$edge['id']} não corresponde a nenhuma opção declarada em {$sourceId}.";
                    }
                }
            }
        }

        // Reachability: every non-end node must be able to reach an end by
        // following any of its outgoing edges.
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'end') {
                continue;
            }
            if (! $this->canReachEnd($node['id'], $nodesById, $outgoingBySource)) {
                $errors['nodes'] = "O nó {$node['id']} não alcança nenhum nó final.";
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /**
     * Verifica se um nó consegue alcançar pelo menos um nó final seguindo as arestas do grafo.
     *
     * @param  string  $startId
     * @param  array<string, array<string, mixed>>  $nodesById
     * @param  array<string, array<int, array<string, mixed>>>  $outgoingBySource
     * @return bool
     */
    private function canReachEnd(string $startId, array $nodesById, array $outgoingBySource): bool
    {
        $stack = [$startId];
        $seen = [];
        while ($stack) {
            $id = array_pop($stack);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $node = $nodesById[$id] ?? null;
            if (! $node) {
                continue;
            }
            if (($node['type'] ?? null) === 'end') {
                return true;
            }
            foreach ($outgoingBySource[$id] ?? [] as $edge) {
                $stack[] = $edge['target'];
            }
        }

        return false;
    }

    /**
     * Alterna o estado ativo/inativo de um roteiro de perguntas.
     *
     * @param  \App\Models\QuestionScript  $questionScript
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleActive(QuestionScript $questionScript): RedirectResponse
    {
        $this->authorize('toggleActive', $questionScript);

        // If activating this script, deactivate all others
        if (!$questionScript->is_active) {
            QuestionScript::where('id', '!=', $questionScript->id)
                ->update(['is_active' => false]);
        }

        $questionScript->update(['is_active' => !$questionScript->is_active]);

        $status = $questionScript->is_active ? 'ativado' : 'desativado';

        return redirect()->route('question-scripts.index')
            ->with('success', "Roteiro {$status} com sucesso!");
    }
}
