<?php

namespace App\Http\Controllers;

use App\Models\QuestionScript;
use Illuminate\Http\Request;

class AdminQuestionScriptController extends Controller
{
    /**
     * List all question scripts.
     */
    public function index()
    {
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
     * Show a specific question script with its full structure.
     */
    public function show(QuestionScript $questionScript)
    {
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
     * Update a question script (nodes, edges, metadata).
     */
    public function update(Request $request, QuestionScript $questionScript)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'nodes' => 'required|array',
            'nodes.*.id' => 'required|string',
            'nodes.*.type' => 'required|string|in:start,question,end',
            'nodes.*.position' => 'required|array',
            'nodes.*.data.message' => 'required|string',
            'edges' => 'required|array',
            'edges.*.id' => 'required|string',
            'edges.*.source' => 'required|string',
            'edges.*.target' => 'required|string',
        ]);

        $questionScript->update($validated);

        return redirect()->route('question-scripts.show', $questionScript->id)
            ->with('success', 'Roteiro atualizado com sucesso!');
    }

    /**
     * Toggle active status of a question script.
     */
    public function toggleActive(QuestionScript $questionScript)
    {
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
