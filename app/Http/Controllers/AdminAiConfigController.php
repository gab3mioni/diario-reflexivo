<?php

namespace App\Http\Controllers;

use App\Models\AiProviderConfig;
use App\Models\AnalysisPrompt;
use App\Services\AiProviders\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->first();
        $latestVersion = $prompt?->latestVersion;

        $versions = $prompt
            ? $prompt->versions()
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
                ])
            : [];

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
            'currentPrompt' => $latestVersion ? [
                'id' => $latestVersion->id,
                'version' => $latestVersion->version,
                'content' => $latestVersion->content,
            ] : null,
            'promptVersions' => $versions,
        ]);
    }

    /**
     * Atualiza a configuração do provedor de IA ativo.
     *
     * @param  \Illuminate\Http\Request  $request
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
     * @param  \Illuminate\Http\Request  $request
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
     * Cria uma nova versão do prompt de análise de diário.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePrompt(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->firstOrFail();

        $prompt->createVersion($validated['content'], Auth::id());

        return redirect()->route('ai-config.index')
            ->with('success', 'Prompt atualizado com sucesso! Nova versão criada.');
    }

    /**
     * Retorna o histórico paginado de versões do prompt de análise.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function promptHistory()
    {
        $prompt = AnalysisPrompt::where('slug', 'diary-analysis')->firstOrFail();

        $versions = $prompt->versions()
            ->with('creator:id,name')
            ->orderByDesc('version')
            ->paginate(20);

        return response()->json($versions);
    }
}
