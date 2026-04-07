import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, FileText, MessageSquare, Power, PowerOff } from 'lucide-react';

interface ScriptSummary {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    questions_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    scripts: ScriptSummary[];
}

export default function AdminQuestionScriptsIndex({ scripts }: Props) {
    const breadcrumbs = [{ title: 'Roteiros de Perguntas', href: '/question-scripts' }];

    const handleToggleActive = (scriptId: number) => {
        router.post(route('question-scripts.toggle-active', scriptId));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roteiros de Perguntas" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Roteiros de Perguntas</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Gerencie os roteiros de perguntas usados no diário reflexivo dos alunos.
                    </p>
                </div>

                {scripts.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <FileText className="h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 mt-4 text-lg font-semibold">Nenhum roteiro cadastrado</h3>
                            <p className="text-sm text-muted-foreground">
                                Crie um roteiro de perguntas para que os alunos possam preencher o diário reflexivo.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {scripts.map((script) => (
                            <Card key={script.id} className={script.is_active ? 'border-green-500/50' : ''}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{script.name}</CardTitle>
                                            {script.description && (
                                                <CardDescription className="mt-1">
                                                    {script.description}
                                                </CardDescription>
                                            )}
                                        </div>
                                        <Badge variant={script.is_active ? 'default' : 'outline'}>
                                            {script.is_active ? 'Ativo' : 'Inativo'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-4 text-sm text-muted-foreground mb-4">
                                        <span className="flex items-center gap-1">
                                            <MessageSquare className="h-3.5 w-3.5" />
                                            {script.questions_count} pergunta{script.questions_count !== 1 ? 's' : ''}
                                        </span>
                                        <span>
                                            Atualizado em{' '}
                                            {new Date(script.updated_at).toLocaleDateString('pt-BR', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Link href={route('question-scripts.show', script.id)}>
                                            <Button variant="outline" size="sm">
                                                <Eye className="h-3.5 w-3.5 mr-1.5" />
                                                Visualizar
                                            </Button>
                                        </Link>
                                        <Button
                                            variant={script.is_active ? 'destructive' : 'default'}
                                            size="sm"
                                            onClick={() => handleToggleActive(script.id)}
                                        >
                                            {script.is_active ? (
                                                <>
                                                    <PowerOff className="h-3.5 w-3.5 mr-1.5" />
                                                    Desativar
                                                </>
                                            ) : (
                                                <>
                                                    <Power className="h-3.5 w-3.5 mr-1.5" />
                                                    Ativar
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
