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
    RotateCcw,
    Save,
    TriangleAlert,
    Zap,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
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

interface CurrentPrompt {
    id: number;
    version: number;
    content: string;
}

interface Props {
    providerConfig: ProviderConfig | null;
    currentPrompt: CurrentPrompt | null;
    promptVersions: PromptVersion[];
}

type TabKey = 'provider' | 'prompt' | 'history';

const providerMeta: Record<
    ProviderConfig['provider'],
    { label: string; modelPlaceholder: string; baseUrlPlaceholder: string; needsKey: boolean }
> = {
    openai: {
        label: 'OpenAI',
        modelPlaceholder: 'gpt-4o',
        baseUrlPlaceholder: 'https://api.openai.com',
        needsKey: true,
    },
    gemini: {
        label: 'Google Gemini',
        modelPlaceholder: 'gemini-1.5-pro',
        baseUrlPlaceholder: 'https://generativelanguage.googleapis.com',
        needsKey: true,
    },
    ollama: {
        label: 'Ollama (Local)',
        modelPlaceholder: 'llama3',
        baseUrlPlaceholder: 'http://localhost:11434',
        needsKey: false,
    },
};

const breadcrumbs = [{ title: 'Configuração IA', href: '/ai-config' }];

export default function AdminAiConfigIndex({ providerConfig, currentPrompt, promptVersions }: Props) {
    const [tab, setTab] = useState<TabKey>('provider');
    const [showKey, setShowKey] = useState(false);
    const [testing, setTesting] = useState(false);

    const providerForm = useForm({
        provider: providerConfig?.provider || 'openai',
        model: providerConfig?.model || '',
        temperature: providerConfig?.temperature?.toString() || '0.7',
        api_key: '',
        base_url: providerConfig?.base_url || '',
    });

    const promptForm = useForm({
        content: currentPrompt?.content || '',
    });


    const meta = providerMeta[providerForm.data.provider];
    const temperatureNum = parseFloat(providerForm.data.temperature) || 0;

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
                        { key: 'prompt' as const, label: 'Prompt', icon: FileText },
                        { key: 'history' as const, label: `Histórico (${promptVersions.length})`, icon: History },
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
                                    <Label htmlFor="model">Modelo</Label>
                                    <Input
                                        id="model"
                                        value={providerForm.data.model}
                                        onChange={(e) => providerForm.setData('model', e.target.value)}
                                        placeholder={meta.modelPlaceholder}
                                    />
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
                    <form onSubmit={handlePromptSubmit} className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>Prompt de análise</CardTitle>
                                        <CardDescription>
                                            Instrução enviada ao modelo junto com a resposta do aluno.
                                            {currentPrompt && (
                                                <>
                                                    {' '}
                                                    Versão atual:{' '}
                                                    <strong>v{currentPrompt.version}</strong>
                                                </>
                                            )}
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
                                    placeholder="Digite o prompt de análise..."
                                />
                                {promptForm.errors.content && (
                                    <p className="text-sm text-destructive">{promptForm.errors.content}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Ao salvar, uma nova versão é criada. Versões anteriores ficam no histórico.
                                </p>
                            </CardContent>
                        </Card>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={promptForm.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Salvar nova versão
                            </Button>
                        </div>
                    </form>
                )}

                {/* History tab */}
                {tab === 'history' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Histórico de versões</CardTitle>
                            <CardDescription>
                                Restaure uma versão anterior para editá-la como nova versão.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {promptVersions.length === 0 ? (
                                <EmptyState
                                    compact
                                    icon={<History />}
                                    title="Nenhuma versão ainda"
                                    description="Salve um prompt na aba Prompt para iniciar o histórico."
                                />
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {promptVersions.map((version) => {
                                        const isCurrent = version.version === currentPrompt?.version;
                                        return (
                                            <li
                                                key={version.id}
                                                className="rounded-lg border border-border/60 bg-card p-4"
                                            >
                                                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline">v{version.version}</Badge>
                                                        {isCurrent && (
                                                            <StatusBadge tone="done" size="sm">
                                                                Atual
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
                                                {!isCurrent && (
                                                    <div className="mt-2 flex justify-end">
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
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
