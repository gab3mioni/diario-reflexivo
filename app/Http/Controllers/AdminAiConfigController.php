<?php

namespace App\Http\Controllers;

use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Models\AnalysisPromptVersion;
use App\Services\AiProviders\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Controlador de configuração de IA para administradores.
 */
class AdminAiConfigController extends Controller
{
    /**
     * Exibe a página de configuração do provedor de IA e do prompt de análise.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $providerConfig = AiProviderConfig::active();

        $prompts = AnalysisPrompt::whereIn('slug', ['diary-analysis', 'branch-classifier'])
            ->with(['activeVersion', 'latestVersion'])
            ->get()
            ->keyBy('slug');

        return inertia('admin/ai-config/index', [
            'providerConfig' => $providerConfig ? [
                'id' => $providerConfig->id,
                'provider' => $providerConfig->provider,
                'model' => $providerConfig->model,
                'temperature' => $providerConfig->temperature,
                'base_url' => $providerConfig->base_url,
                'has_api_key' => ! empty($providerConfig->api_key),
                'is_active' => $providerConfig->is_active,
            ] : null,
            'prompts' => [
                'diary-analysis' => $this->serializePrompt($prompts->get('diary-analysis')),
                'branch-classifier' => $this->serializePrompt($prompts->get('branch-classifier')),
            ],
        ]);
    }

    /**
     * Serializa um prompt para a UI, com versão em uso (pin → fallback latest) e histórico.
     *
     * @return ?array<string, mixed>
     */
    private function serializePrompt(?AnalysisPrompt $prompt): ?array
    {
        if (! $prompt) {
            return null;
        }

        $resolved = $prompt->resolveActiveVersion();

        $versions = $prompt->versions()
            ->with('creator:id,name')
            ->orderByDesc('version')
            ->limit(20)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'content' => $v->content,
                'created_by_name' => $v->creator?->name,
                'created_at' => $v->created_at->toISOString(),
            ]);

        return [
            'id' => $prompt->id,
            'slug' => $prompt->slug,
            'name' => $prompt->name,
            'description' => $prompt->description,
            'active_version_id' => $prompt->active_version_id,
            'resolved_version' => $resolved ? [
                'id' => $resolved->id,
                'version' => $resolved->version,
                'content' => $resolved->content,
            ] : null,
            'is_pinned' => $prompt->active_version_id !== null,
            'versions' => $versions,
        ];
    }

    /**
     * Atualiza a configuração do provedor de IA ativo.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateProvider(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:openai,gemini,ollama',
            'model' => 'required|string|max:100',
            'temperature' => 'required|numeric|min:0|max:2',
            'api_key' => 'nullable|string|max:500',
            'base_url' => 'nullable|string|max:500|url:http,https',
        ]);

        // Deactivate all others
        AiProviderConfig::where('is_active', true)->update(['is_active' => false]);

        $config = AiProviderConfig::where('slug', 'default')->first();

        $data = [
            'slug' => 'default',
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'temperature' => $validated['temperature'],
            'base_url' => $validated['base_url'],
            'is_active' => true,
        ];

        // Only update api_key if provided (don't clear it with empty string)
        if (! empty($validated['api_key'])) {
            $data['api_key'] = $validated['api_key'];
        }

        if ($config) {
            $config->update($data);
        } else {
            AiProviderConfig::create($data);
        }

        return redirect()->route('ai-config.index')
            ->with('success', 'Configuração do provedor atualizada com sucesso!');
    }

    /**
     * Testa a conexão com o provedor de IA usando os dados informados.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:openai,gemini,ollama',
            'model' => 'required|string|max:100',
            'temperature' => 'required|numeric|min:0|max:2',
            'api_key' => 'nullable|string|max:500',
            'base_url' => 'nullable|string|max:500|url:http,https',
        ]);

        $apiKey = $validated['api_key'] ?? null;
        if (empty($apiKey)) {
            $existing = AiProviderConfig::where('slug', 'default')->first();
            if ($existing && $existing->provider === $validated['provider']) {
                $apiKey = $existing->api_key;
            }
        }

        $tempConfig = new AiProviderConfig([
            'slug' => 'default',
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'temperature' => $validated['temperature'],
            'api_key' => $apiKey,
            'base_url' => $validated['base_url'] ?? null,
            'is_active' => true,
        ]);

        try {
            AiProvider::fromConfig($tempConfig)->ping();
        } catch (Throwable $e) {
            Log::warning('AI provider ping failed', [
                'provider' => $validated['provider'],
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível conectar ao provedor de IA. Verifique os dados informados.');
        }

        return back()->with('success', 'Conexão com o provedor estabelecida com sucesso.');
    }

    /**
     * Cria uma nova versão de um prompt (identificado por slug).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePrompt(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|in:diary-analysis,branch-classifier',
            'content' => 'required|string|max:10000',
        ]);

        $prompt = AnalysisPrompt::where('slug', $validated['slug'])->firstOrFail();

        $prompt->createVersion($validated['content'], Auth::id());

        return redirect()->route('ai-config.index')
            ->with('success', 'Prompt atualizado com sucesso! Nova versão criada.');
    }

    /**
     * Fixa (ou limpa) qual versão de um prompt deve ser usada em runtime.
     *
     * Aceita um version_id pertencente ao prompt, ou null para voltar ao
     * comportamento padrão (latestVersion).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setActiveVersion(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string|in:diary-analysis,branch-classifier',
            'version_id' => 'nullable|integer|exists:analysis_prompt_versions,id',
        ]);

        $prompt = AnalysisPrompt::where('slug', $validated['slug'])->firstOrFail();

        if ($validated['version_id'] !== null) {
            $belongs = AnalysisPromptVersion::where('id', $validated['version_id'])
                ->where('analysis_prompt_id', $prompt->id)
                ->exists();

            if (! $belongs) {
                return back()->with('error', 'A versão selecionada não pertence a este prompt.');
            }
        }

        $prompt->promoteVersion($validated['version_id'], Auth::id());

        $message = $validated['version_id'] === null
            ? 'Versão ativa liberada. O sistema voltará a usar a versão mais recente automaticamente.'
            : 'Versão ativa atualizada com sucesso.';

        return redirect()->route('ai-config.index')->with('success', $message);
    }

    /**
     * Lista os modelos disponíveis em uma instância do Ollama.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ollamaModels(Request $request)
    {
        $baseUrl = $request->query('base_url');
        if (empty($baseUrl)) {
            $existing = AiProviderConfig::where('slug', 'default')->first();
            $baseUrl = $existing?->base_url;
        }
        $baseUrl = $baseUrl ?: 'http://host.docker.internal:11434';
        $baseUrl = rtrim($baseUrl, '/');

        try {
            $response = Http::timeout(8)->get("{$baseUrl}/api/tags");
        } catch (Throwable $e) {
            return response()->json([
                'models' => [],
                'error' => 'Não foi possível conectar ao Ollama.',
            ], 200);
        }

        if ($response->failed()) {
            return response()->json([
                'models' => [],
                'error' => "Ollama respondeu com HTTP {$response->status()}.",
            ], 200);
        }

        $models = collect($response->json('models') ?? [])
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return response()->json(['models' => $models]);
    }

    /**
     * Retorna o histórico paginado de versões de um prompt (identificado por slug na query).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function promptHistory(Request $request)
    {
        $slug = $request->query('slug', 'diary-analysis');

        $prompt = AnalysisPrompt::where('slug', $slug)->firstOrFail();

        $versions = $prompt->versions()
            ->with('creator:id,name')
            ->orderByDesc('version')
            ->paginate(20);

        return response()->json($versions);
    }
}
