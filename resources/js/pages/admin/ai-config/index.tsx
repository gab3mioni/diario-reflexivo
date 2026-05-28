import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { EmptyState } from '@/components/empty-state';
import { UnsavedChangesGuard } from '@/components/unsaved-changes-guard';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Bot,
    Eye,
    EyeOff,
    FileText,
    History,
    Pin,
    PinOff,
    RefreshCw,
    RotateCcw,
    Save,
    TriangleAlert,
    Zap,
} from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { cn } from '@/lib/utils';

interface ProviderConfig {
    id: number;
    provider: 'openai' | 'gemini' | 'ollama';
    model: string;
    temperature: number;
    base_url: string | null;
    has_api_key: boolean;
    is_active: boolean;
}

interface PromptVersion {
    id: number;
    version: number;
    content: string;
    created_by_name: string | null;
    created_at: string;
}

interface ResolvedVersion {
    id: number;
    version: number;
    content: string;
}

interface PromptData {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    active_version_id: number | null;
    resolved_version: ResolvedVersion | null;
    is_pinned: boolean;
    versions: PromptVersion[];
}

type PromptSlug = 'diary-analysis' | 'branch-classifier';

interface Props {
    providerConfig: ProviderConfig | null;
    prompts: Record<PromptSlug, PromptData | null>;
}

type TabKey = 'provider' | 'prompt' | 'history';

const promptLabel: Record<PromptSlug, string> = {
    'diary-analysis': 'Análise de diário',
    'branch-classifier': 'Classificador de fluxo',
};

const providerMeta: Record<
    ProviderConfig['provider'],
    {
        label: string;
        modelPlaceholder: string;
        baseUrlPlaceholder: string;
        needsKey: boolean;
        models: string[];
    }
> = {
    openai: {
        label: 'OpenAI',
        modelPlaceholder: 'gpt-4o',
        baseUrlPlaceholder: 'https://api.openai.com',
        needsKey: true,
        models: [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
            'o1-mini',
            'o3-mini',
        ],
    },
    gemini: {
        label: 'Google Gemini',
        modelPlaceholder: 'gemini-1.5-pro',
        baseUrlPlaceholder: 'https://generativelanguage.googleapis.com',
        needsKey: true,
        models: [
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b',
        ],
    },
    ollama: {
        label: 'Ollama (Local)',
        modelPlaceholder: 'llama3',
        baseUrlPlaceholder: 'http://localhost:11434',
        needsKey: false,
        models: [],
    },
};

const CUSTOM_MODEL_VALUE = '__custom__';
const LATEST_SENTINEL = '__latest__';

const breadcrumbs = [{ title: 'Configuração IA', href: '/ai-config' }];

export default function AdminAiConfigIndex({ providerConfig, prompts }: Props) {
    const [tab, setTab] = useState<TabKey>('provider');
    const [selectedSlug, setSelectedSlug] = useState<PromptSlug>('diary-analysis');
    const [showKey, setShowKey] = useState(false);
    const [testing, setTesting] = useState(false);
    const [ollamaModels, setOllamaModels] = useState<string[]>([]);
    const [ollamaLoading, setOllamaLoading] = useState(false);
    const [ollamaError, setOllamaError] = useState<string | null>(null);
    const [customModel, setCustomModel] = useState(false);

    const selectedPrompt = prompts[selectedSlug];

    const providerForm = useForm({
        provider: providerConfig?.provider || 'openai',
        model: providerConfig?.model || '',
        temperature: providerConfig?.temperature?.toString() || '0.7',
        api_key: '',
        base_url: providerConfig?.base_url || '',
    });

    const promptForm = useForm({
        slug: selectedSlug,
        content: selectedPrompt?.resolved_version?.content || '',
    });

    useEffect(() => {
        promptForm.setData('slug', selectedSlug);
        promptForm.setData('content', selectedPrompt?.resolved_version?.content || '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedSlug]);

    const meta = providerMeta[providerForm.data.provider];
    const temperatureNum = parseFloat(providerForm.data.temperature) || 0;

    const availableModels =
        providerForm.data.provider === 'ollama' ? ollamaModels : meta.models;

    const fetchOllamaModels = () => {
        setOllamaLoading(true);
        setOllamaError(null);
        const params = providerForm.data.base_url
            ? `?base_url=${encodeURIComponent(providerForm.data.base_url)}`
            : '';
        fetch(`${route('ai-config.ollama-models')}${params}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data: { models?: string[]; error?: string }) => {
                setOllamaModels(data.models ?? []);
                setOllamaError(data.error ?? null);
            })
            .catch(() => setOllamaError('Falha ao buscar modelos do Ollama.'))
            .finally(() => setOllamaLoading(false));
    };

    useEffect(() => {
        if (providerForm.data.provider === 'ollama') {
            fetchOllamaModels();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [providerForm.data.provider]);

    useEffect(() => {
        if (providerForm.data.provider === 'ollama') return;
        const known = providerMeta[providerForm.data.provider].models;
        if (providerForm.data.model && !known.includes(providerForm.data.model)) {
            setCustomModel(true);
        } else {
            setCustomModel(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [providerForm.data.provider]);

    const handleProviderSubmit = (e: FormEvent) => {
        e.preventDefault();
        providerForm.put(route('ai-config.update-provider'));
    };

    const handlePromptSubmit = (e: FormEvent) => {
        e.preventDefault();
        promptForm.put(route('ai-config.update-prompt'));
    };

    const handleTestConnection = () => {
        setTesting(true);
        router.post(route('ai-config.test'), providerForm.data, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setTesting(false),
        });
    };

    const handleSetActiveVersion = (versionId: number | null) => {
        router.put(
            route('ai-config.set-active-version'),
            { slug: selectedSlug, version_id: versionId },
            { preserveScroll: true },
        );
    };

    const totalHistory = useMemo(
        () =>
            (prompts['diary-analysis']?.versions.length ?? 0) +
            (prompts['branch-classifier']?.versions.length ?? 0),
        [prompts],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuração IA" />
            <UnsavedChangesGuard dirty={providerForm.isDirty || promptForm.isDirty} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Configuração IA"
                    description="Provedor, parâmetros e prompts usados na análise dos diários reflexivos."
                    icon={<Bot />}
                    actions={
                        providerConfig?.is_active ? (
                            <StatusBadge tone="done" dot pulse>
                                {providerMeta[providerConfig.provider].label}
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="warning" icon={<TriangleAlert aria-hidden="true" />}>
                                Sem provedor ativo
                            </StatusBadge>
                        )
                    }
                />

                {/* Tabs */}
                <div className="flex gap-1 overflow-x-auto border-b border-border/60" role="tablist">
                    {[
                        { key: 'provider' as const, label: 'Provedor', icon: Bot },
                        { key: 'prompt' as const, label: 'Prompts', icon: FileText },
                        { key: 'history' as const, label: `Histórico (${totalHistory})`, icon: History },
                    ].map(({ key, label, icon: Icon }) => (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            className={cn(
                                'inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                                tab === key
                                    ? 'border-foreground text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            {label}
                        </button>
                    ))}
                </div>

                {/* Provider tab */}
                {tab === 'provider' && (
                    <form onSubmit={handleProviderSubmit} className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Provedor</CardTitle>
                                <CardDescription>
                                    Escolha o serviço que fará a análise dos diários.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {(Object.keys(providerMeta) as Array<ProviderConfig['provider']>).map(
                                        (key) => {
                                            const active = providerForm.data.provider === key;
                                            return (
                                                <button
                                                    key={key}
                                                    type="button"
                                                    onClick={() => providerForm.setData('provider', key)}
                                                    className={cn(
                                                        'flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition-all',
                                                        active
                                                            ? 'border-foreground bg-muted/60 ring-2 ring-foreground/10'
                                                            : 'border-border/60 hover:border-border hover:bg-muted/30',
                                                    )}
                                                    aria-pressed={active}
                                                >
                                                    <span className="font-medium">
                                                        {providerMeta[key].label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {providerMeta[key].needsKey
                                                            ? 'Requer chave de API'
                                                            : 'Execução local'}
                                                    </span>
                                                </button>
                                            );
                                        },
                                    )}
                                </div>
                                {providerForm.errors.provider && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {providerForm.errors.provider}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Parâmetros do modelo</CardTitle>
                                <CardDescription>
                                    Controle de modelo e criatividade da geração.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="model">Modelo</Label>
                                        {providerForm.data.provider === 'ollama' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={fetchOllamaModels}
                                                disabled={ollamaLoading}
                                            >
                                                {ollamaLoading ? (
                                                    <Spinner className="size-3.5" />
                                                ) : (
                                                    <RefreshCw className="size-3.5" />
                                                )}
                                                Recarregar
                                            </Button>
                                        )}
                                    </div>
                                    {customModel ? (
                                        <div className="flex gap-2">
                                            <Input
                                                id="model"
                                                value={providerForm.data.model}
                                                onChange={(e) =>
                                                    providerForm.setData('model', e.target.value)
                                                }
                                                placeholder={meta.modelPlaceholder}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setCustomModel(false);
                                                    providerForm.setData('model', '');
                                                }}
                                            >
                                                Usar lista
                                            </Button>
                                        </div>
                                    ) : (
                                        <Select
                                            value={providerForm.data.model || undefined}
                                            onValueChange={(value) => {
                                                if (value === CUSTOM_MODEL_VALUE) {
                                                    setCustomModel(true);
                                                    providerForm.setData('model', '');
                                                } else {
                                                    providerForm.setData('model', value);
                                                }
                                            }}
                                        >
                                            <SelectTrigger id="model">
                                                <SelectValue
                                                    placeholder={
                                                        providerForm.data.provider === 'ollama' &&
                                                        ollamaLoading
                                                            ? 'Buscando modelos...'
                                                            : availableModels.length === 0
                                                              ? 'Nenhum modelo disponível'
                                                              : 'Selecione um modelo'
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableModels.map((model) => (
                                                    <SelectItem key={model} value={model}>
                                                        {model}
                                                    </SelectItem>
                                                ))}
                                                <SelectItem value={CUSTOM_MODEL_VALUE}>
                                                    Personalizado...
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                    {providerForm.data.provider === 'ollama' && ollamaError && (
                                        <p className="text-xs text-muted-foreground">
                                            {ollamaError} Verifique a URL base e clique em Recarregar.
                                        </p>
                                    )}
                                    {providerForm.errors.model && (
                                        <p className="text-sm text-destructive">{providerForm.errors.model}</p>
                                    )}
                                </div>

                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="temperature">
                                            Criatividade{' '}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                (temperatura)
                                            </span>
                                        </Label>
                                        <span className="text-sm font-medium tabular-nums">
                                            {temperatureNum.toFixed(1)}
                                        </span>
                                    </div>
                                    <input
                                        id="temperature"
                                        type="range"
                                        min="0"
                                        max="2"
                                        step="0.1"
                                        value={providerForm.data.temperature}
                                        onChange={(e) => providerForm.setData('temperature', e.target.value)}
                                        className="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted accent-foreground"
                                    />
                                    <div className="flex justify-between text-[11px] text-muted-foreground">
                                        <span>Preciso</span>
                                        <span>Equilibrado</span>
                                        <span>Criativo</span>
                                    </div>
                                    {providerForm.errors.temperature && (
                                        <p className="text-sm text-destructive">
                                            {providerForm.errors.temperature}
                                        </p>
                                    )}
                                </div>

                                {meta.needsKey && (
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="api_key">
                                            Chave de API
                                            {providerConfig?.has_api_key && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    (configurada — deixe vazio para manter)
                                                </span>
                                            )}
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="api_key"
                                                type={showKey ? 'text' : 'password'}
                                                value={providerForm.data.api_key}
                                                onChange={(e) => providerForm.setData('api_key', e.target.value)}
                                                placeholder="sk-..."
                                                className="pr-10"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowKey((v) => !v)}
                                                className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                                aria-label={showKey ? 'Ocultar chave' : 'Mostrar chave'}
                                            >
                                                {showKey ? (
                                                    <EyeOff className="size-4" />
                                                ) : (
                                                    <Eye className="size-4" />
                                                )}
                                            </button>
                                        </div>
                                        {providerForm.errors.api_key && (
                                            <p className="text-sm text-destructive">
                                                {providerForm.errors.api_key}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="base_url">
                                        URL base{' '}
                                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                                            (opcional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="base_url"
                                        value={providerForm.data.base_url}
                                        onChange={(e) => providerForm.setData('base_url', e.target.value)}
                                        placeholder={meta.baseUrlPlaceholder}
                                    />
                                    {providerForm.errors.base_url && (
                                        <p className="text-sm text-destructive">
                                            {providerForm.errors.base_url}
                                        </p>
                                    )}
                                </div>

                                {/* Test connection */}
                                <div className="flex items-center justify-between gap-3 rounded-lg border border-dashed border-border/70 bg-muted/30 p-4">
                                    <div>
                                        <p className="text-sm font-medium">Testar conexão</p>
                                        <p className="text-xs text-muted-foreground">
                                            Envia uma requisição de ping ao provedor com os parâmetros atuais.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleTestConnection}
                                        disabled={testing}
                                    >
                                        {testing ? <Spinner className="size-4" /> : <Zap className="size-4" />}
                                        Testar
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={providerForm.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Salvar configuração
                            </Button>
                        </div>
                    </form>
                )}

                {/* Prompt tab */}
                {tab === 'prompt' && (
                    <div className="flex flex-col gap-4">
                        <PromptSwitcher
                            selected={selectedSlug}
                            onChange={setSelectedSlug}
                            prompts={prompts}
                        />

                        {selectedPrompt ? (
                            <>
                                <ActiveVersionCard
                                    prompt={selectedPrompt}
                                    onSetActive={handleSetActiveVersion}
                                />

                                <form onSubmit={handlePromptSubmit} className="flex flex-col gap-4">
                                    <Card>
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle>Editor — {promptLabel[selectedSlug]}</CardTitle>
                                                    <CardDescription>
                                                        Salvar cria uma nova versão. Para colocá-la em uso,
                                                        fixe-a depois no card acima.
                                                    </CardDescription>
                                                </div>
                                                <span className="text-xs tabular-nums text-muted-foreground">
                                                    {promptForm.data.content.length} caracteres
                                                </span>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <textarea
                                                id="prompt_content"
                                                className="flex min-h-[320px] w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-sm leading-relaxed placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                                                value={promptForm.data.content}
                                                onChange={(e) => promptForm.setData('content', e.target.value)}
                                                placeholder="Digite o prompt..."
                                            />
                                            {promptForm.errors.content && (
                                                <p className="text-sm text-destructive">
                                                    {promptForm.errors.content}
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={promptForm.processing}>
                                            <Save className="size-4" aria-hidden="true" />
                                            Salvar nova versão
                                        </Button>
                                    </div>
                                </form>
                            </>
                        ) : (
                            <EmptyState
                                icon={<FileText />}
                                title="Prompt não encontrado"
                                description="Esse prompt ainda não foi seedado no banco."
                            />
                        )}
                    </div>
                )}

                {/* History tab */}
                {tab === 'history' && (
                    <div className="flex flex-col gap-4">
                        <PromptSwitcher
                            selected={selectedSlug}
                            onChange={setSelectedSlug}
                            prompts={prompts}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Histórico — {promptLabel[selectedSlug]}</CardTitle>
                                <CardDescription>
                                    Fixe uma versão para colocá-la em uso, ou carregue no editor para criar uma
                                    nova com base nela.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!selectedPrompt || selectedPrompt.versions.length === 0 ? (
                                    <EmptyState
                                        compact
                                        icon={<History />}
                                        title="Nenhuma versão ainda"
                                        description="Salve um prompt na aba Prompts para iniciar o histórico."
                                    />
                                ) : (
                                    <ul className="flex flex-col gap-3">
                                        {selectedPrompt.versions.map((version) => {
                                            const isActive =
                                                version.id === selectedPrompt.resolved_version?.id;
                                            const isPinned =
                                                selectedPrompt.is_pinned &&
                                                version.id === selectedPrompt.active_version_id;
                                            return (
                                                <li
                                                    key={version.id}
                                                    className="rounded-lg border border-border/60 bg-card p-4"
                                                >
                                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant="outline">v{version.version}</Badge>
                                                            {isActive && (
                                                                <StatusBadge tone="done" size="sm">
                                                                    Em uso
                                                                </StatusBadge>
                                                            )}
                                                            {isPinned && (
                                                                <StatusBadge tone="info" size="sm" icon={<Pin aria-hidden="true" />}>
                                                                    Fixada
                                                                </StatusBadge>
                                                            )}
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">
                                                            {version.created_by_name || 'Sistema'} ·{' '}
                                                            {new Date(version.created_at).toLocaleDateString(
                                                                'pt-BR',
                                                                {
                                                                    day: '2-digit',
                                                                    month: '2-digit',
                                                                    year: 'numeric',
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </span>
                                                    </div>
                                                    <pre className="max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-muted p-3 font-mono text-xs">
                                                        {version.content}
                                                    </pre>
                                                    <div className="mt-2 flex flex-wrap justify-end gap-2">
                                                        {!isPinned && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleSetActiveVersion(version.id)}
                                                            >
                                                                <Pin className="size-3.5" aria-hidden="true" />
                                                                Fixar como ativa
                                                            </Button>
                                                        )}
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                promptForm.setData('content', version.content);
                                                                setTab('prompt');
                                                            }}
                                                        >
                                                            <RotateCcw className="size-3.5" aria-hidden="true" />
                                                            Carregar no editor
                                                        </Button>
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

interface PromptSwitcherProps {
    selected: PromptSlug;
    onChange: (slug: PromptSlug) => void;
    prompts: Record<PromptSlug, PromptData | null>;
}

function PromptSwitcher({ selected, onChange, prompts }: PromptSwitcherProps) {
    const slugs: PromptSlug[] = ['diary-analysis', 'branch-classifier'];
    return (
        <div className="inline-flex gap-1 rounded-lg border border-border/60 bg-muted/30 p-1">
            {slugs.map((slug) => {
                const active = slug === selected;
                const prompt = prompts[slug];
                return (
                    <button
                        key={slug}
                        type="button"
                        onClick={() => onChange(slug)}
                        aria-pressed={active}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            active
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {promptLabel[slug]}
                        {prompt?.is_pinned && (
                            <Pin className="size-3 text-muted-foreground" aria-hidden="true" />
                        )}
                    </button>
                );
            })}
        </div>
    );
}

interface ActiveVersionCardProps {
    prompt: PromptData;
    onSetActive: (versionId: number | null) => void;
}

function ActiveVersionCard({ prompt, onSetActive }: ActiveVersionCardProps) {
    const resolved = prompt.resolved_version;
    const latest = prompt.versions[0];

    const currentValue = prompt.is_pinned && prompt.active_version_id
        ? String(prompt.active_version_id)
        : LATEST_SENTINEL;

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            Versão em uso
                            {prompt.is_pinned ? (
                                <StatusBadge tone="info" size="sm" icon={<Pin aria-hidden="true" />}>
                                    Fixada
                                </StatusBadge>
                            ) : (
                                <StatusBadge tone="done" size="sm">
                                    Última automática
                                </StatusBadge>
                            )}
                        </CardTitle>
                        <CardDescription>
                            {resolved
                                ? `v${resolved.version} está respondendo às chamadas do classificador.`
                                : 'Esse prompt ainda não tem nenhuma versão.'}
                        </CardDescription>
                    </div>
                    {prompt.is_pinned && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onSetActive(null)}
                        >
                            <PinOff className="size-3.5" aria-hidden="true" />
                            Soltar fixação
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <div className="flex flex-col gap-2">
                    <Label htmlFor={`active-version-${prompt.slug}`}>Trocar versão ativa</Label>
                    <Select
                        value={currentValue}
                        onValueChange={(value) =>
                            onSetActive(value === LATEST_SENTINEL ? null : parseInt(value, 10))
                        }
                    >
                        <SelectTrigger id={`active-version-${prompt.slug}`}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={LATEST_SENTINEL}>
                                Sempre a mais recente {latest ? `(hoje v${latest.version})` : ''}
                            </SelectItem>
                            {prompt.versions.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    v{v.version} — {new Date(v.created_at).toLocaleDateString('pt-BR')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        Use a fixação para rodar uma versão específica em produção (ex.: comparar v2 vs v3) sem
                        precisar de deploy. Solte para voltar ao comportamento padrão.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
