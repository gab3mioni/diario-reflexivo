import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AnalysisCard } from '@/components/analysis-card';
import { ChatHistory } from '@/components/chat-history';
import AppLayout from '@/layouts/app-layout';
import type { ChatMessage, DiaryAnalysis } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bot, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface Props {
    lesson: {
        id: number;
        title: string;
        subject: { id: number; name: string };
    };
    student: {
        id: number;
        name: string;
        email: string;
    };
    response: {
        id: number;
        content: string;
        submitted_at: string;
    };
    chatMessages: ChatMessage[];
    analyses: DiaryAnalysis[];
    canReanalyze: boolean;
}

export default function TeacherLessonsAnalysis({
    lesson,
    student,
    response,
    chatMessages,
    analyses,
    canReanalyze,
}: Props) {
    const breadcrumbs = [
        { title: 'Aulas', href: '/lessons' },
        { title: lesson.title, href: `/lessons/${lesson.id}` },
        { title: `Análise - ${student.name}`, href: '#' },
    ];

    const [reanalyzing, setReanalyzing] = useState(false);

    const handleReanalyze = () => {
        setReanalyzing(true);
        router.post(
            route('diaries.analyze', {
                response: response.id,
            }),
            {},
            {
                onFinish: () => setReanalyzing(false),
            },
        );
    };

    const maxAnalyses = 3;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Análise - ${student.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <Link href={route('lessons.show', lesson.id)}>
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Análise do Diário
                            </h1>
                            <div className="flex items-center gap-2 mt-1">
                                <Badge variant="secondary">{lesson.subject.name}</Badge>
                                <span className="text-sm text-muted-foreground">
                                    {student.name} ({student.email})
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">
                            {analyses.length}/{maxAnalyses} análises
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!canReanalyze || reanalyzing}
                            onClick={handleReanalyze}
                        >
                            <RefreshCw className={`h-4 w-4 mr-1.5 ${reanalyzing ? 'animate-spin' : ''}`} />
                            Re-analisar
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Left: Chat History */}
                    <Card className="lg:sticky lg:top-4 lg:self-start">
                        <CardHeader>
                            <CardTitle>Histórico de Conversa</CardTitle>
                            <CardDescription>
                                Respostas do aluno ao diário reflexivo
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="max-h-[600px] overflow-y-auto">
                            {chatMessages.length > 0 ? (
                                <ChatHistory messages={chatMessages} showTimestamps />
                            ) : response.content ? (
                                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                                    <p className="whitespace-pre-wrap">{response.content}</p>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">Sem conteúdo registrado.</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Right: Analyses */}
                    <div className="space-y-4">
                        {analyses.length === 0 ? (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                                    <Bot className="h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 mt-4 text-lg font-semibold">Nenhuma análise realizada</h3>
                                    <p className="text-sm text-muted-foreground mb-4">
                                        Clique em "Re-analisar" para solicitar uma análise por IA.
                                    </p>
                                    <Button
                                        variant="default"
                                        disabled={!canReanalyze || reanalyzing}
                                        onClick={handleReanalyze}
                                    >
                                        <Bot className="h-4 w-4 mr-2" />
                                        Solicitar Análise
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            analyses.map((analysis, index) => (
                                <AnalysisCard
                                    key={analysis.id}
                                    analysis={analysis}
                                    responseId={response.id}
                                    isLatest={index === 0}
                                />
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
