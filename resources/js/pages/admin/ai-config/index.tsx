import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Bot, History, Save } from 'lucide-react';
import { FormEvent } from 'react';

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

export default function AdminAiConfigIndex({ providerConfig, currentPrompt, promptVersions }: Props) {
    const breadcrumbs = [{ title: 'Configuração IA', href: '/ai-config' }];

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

    const handleProviderSubmit = (e: FormEvent) => {
        e.preventDefault();
        providerForm.put(route('ai-config.update-provider'));
    };

    const handlePromptSubmit = (e: FormEvent) => {
        e.preventDefault();
        promptForm.put(route('ai-config.update-prompt'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuração IA" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Configuração IA</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Configure o provedor de inteligência artificial e o prompt de análise dos diários reflexivos.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Provider Configuration */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Bot className="h-5 w-5" />
                                <div>
                                    <CardTitle>Provedor de IA</CardTitle>
                                    <CardDescription>
                                        Configure o provedor, modelo e parâmetros da IA.
                                    </CardDescription>
                                </div>
                            </div>
                            {providerConfig?.is_active && (
                                <Badge variant="default" className="w-fit">Ativo</Badge>
                            )}
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleProviderSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="provider">Provedor</Label>
                                    <Select
                                        value={providerForm.data.provider}
                                        onValueChange={(value) => providerForm.setData('provider', value as 'openai' | 'gemini' | 'ollama')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecione o provedor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="openai">OpenAI</SelectItem>
                                            <SelectItem value="gemini">Google Gemini</SelectItem>
                                            <SelectItem value="ollama">Ollama (Local)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {providerForm.errors.provider && (
                                        <p className="text-sm text-destructive">{providerForm.errors.provider}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="model">Modelo</Label>
                                    <Input
                                        id="model"
                                        value={providerForm.data.model}
                                        onChange={(e) => providerForm.setData('model', e.target.value)}
                                        placeholder={
                                            providerForm.data.provider === 'openai' ? 'gpt-4o' :
                                            providerForm.data.provider === 'gemini' ? 'gemini-1.5-pro' : 'llama3'
                                        }
                                    />
                                    {providerForm.errors.model && (
                                        <p className="text-sm text-destructive">{providerForm.errors.model}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="temperature">Temperatura</Label>
                                    <Input
                                        id="temperature"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="2"
                                        value={providerForm.data.temperature}
                                        onChange={(e) => providerForm.setData('temperature', e.target.value)}
                                    />
                                    {providerForm.errors.temperature && (
                                        <p className="text-sm text-destructive">{providerForm.errors.temperature}</p>
                                    )}
                                </div>

                                {providerForm.data.provider !== 'ollama' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="api_key">
                                            Chave de API
                                            {providerConfig?.has_api_key && (
                                                <span className="ml-2 text-xs text-muted-foreground">(configurada - deixe vazio para manter)</span>
                                            )}
                                        </Label>
                                        <Input
                                            id="api_key"
                                            type="password"
                                            value={providerForm.data.api_key}
                                            onChange={(e) => providerForm.setData('api_key', e.target.value)}
                                            placeholder="sk-..."
                                        />
                                        {providerForm.errors.api_key && (
                                            <p className="text-sm text-destructive">{providerForm.errors.api_key}</p>
                                        )}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="base_url">
                                        URL Base
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {providerForm.data.provider === 'ollama'
                                                ? '(padrão: http://localhost:11434)'
                                                : '(opcional - deixe vazio para usar o padrão)'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="base_url"
                                        value={providerForm.data.base_url}
                                        onChange={(e) => providerForm.setData('base_url', e.target.value)}
                                        placeholder={
                                            providerForm.data.provider === 'ollama' ? 'http://localhost:11434' :
                                            providerForm.data.provider === 'openai' ? 'https://api.openai.com' :
                                            'https://generativelanguage.googleapis.com'
                                        }
                                    />
                                    {providerForm.errors.base_url && (
                                        <p className="text-sm text-destructive">{providerForm.errors.base_url}</p>
                                    )}
                                </div>

                                <Button type="submit" disabled={providerForm.processing}>
                                    <Save className="h-4 w-4 mr-2" />
                                    Salvar Configuração
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Prompt Configuration */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Prompt de Análise</CardTitle>
                                    <CardDescription>
                                        Edite o prompt utilizado para analisar as respostas dos alunos.
                                        {currentPrompt && (
                                            <span className="ml-1">Versão atual: <strong>v{currentPrompt.version}</strong></span>
                                        )}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handlePromptSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="prompt_content">Conteúdo do Prompt</Label>
                                    <textarea
                                        id="prompt_content"
                                        className="flex min-h-[300px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 font-mono"
                                        value={promptForm.data.content}
                                        onChange={(e) => promptForm.setData('content', e.target.value)}
                                        placeholder="Digite o prompt de análise..."
                                    />
                                    {promptForm.errors.content && (
                                        <p className="text-sm text-destructive">{promptForm.errors.content}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Ao salvar, uma nova versão será criada automaticamente. A versão anterior será mantida no histórico.
                                    </p>
                                </div>

                                <Button type="submit" disabled={promptForm.processing}>
                                    <Save className="h-4 w-4 mr-2" />
                                    Salvar Nova Versão
                                </Button>
                            </form>

                            {/* Version History */}
                            {promptVersions.length > 0 && (
                                <div className="mt-6">
                                    <Accordion type="single" collapsible>
                                        <AccordionItem value="history">
                                            <AccordionTrigger className="text-sm">
                                                <span className="flex items-center gap-2">
                                                    <History className="h-4 w-4" />
                                                    Histórico de Versões ({promptVersions.length})
                                                </span>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div className="space-y-3 pt-2">
                                                    {promptVersions.map((version) => (
                                                        <div key={version.id} className="rounded-md border p-3">
                                                            <div className="flex items-center justify-between mb-2">
                                                                <div className="flex items-center gap-2">
                                                                    <Badge variant="outline">v{version.version}</Badge>
                                                                    {version.version === currentPrompt?.version && (
                                                                        <Badge variant="default">Atual</Badge>
                                                                    )}
                                                                </div>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {version.created_by_name || 'Sistema'} -{' '}
                                                                    {new Date(version.created_at).toLocaleDateString('pt-BR', {
                                                                        day: '2-digit',
                                                                        month: '2-digit',
                                                                        year: 'numeric',
                                                                        hour: '2-digit',
                                                                        minute: '2-digit',
                                                                    })}
                                                                </span>
                                                            </div>
                                                            <pre className="text-xs bg-muted p-2 rounded-md overflow-x-auto max-h-32 whitespace-pre-wrap">
                                                                {version.content}
                                                            </pre>
                                                            {version.version !== currentPrompt?.version && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="mt-2"
                                                                    onClick={() => promptForm.setData('content', version.content)}
                                                                >
                                                                    Restaurar esta versão
                                                                </Button>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
