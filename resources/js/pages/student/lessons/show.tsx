import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChatDiary } from '@/components/chat-diary';
import { ChatHistory } from '@/components/chat-history';
import AppLayout from '@/layouts/app-layout';
import type { ChatCurrentNode, ChatMessage, ChatState, Lesson, LessonResponse } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, CheckCircle, Clock, Lock } from 'lucide-react';

interface Props {
    lesson: Lesson;
    response: LessonResponse | null;
    chatMessages: ChatMessage[];
    currentNode: ChatCurrentNode | null;
    totalQuestions: number;
    turnsRemaining: number;
    awaitingFinalCheck: boolean;
    chatState: ChatState;
    draft: string;
}

export default function StudentLessonShow({
    lesson,
    response,
    chatMessages,
    currentNode,
    totalQuestions,
    turnsRemaining,
    awaitingFinalCheck,
    chatState,
    draft,
}: Props) {
    const breadcrumbs = [
        { title: 'Minhas Aulas', href: '/lessons' },
        { title: lesson.title, href: `/lessons/${lesson.id}` },
    ];

    const date = new Date(lesson.scheduled_at);
    const hasResponded = response !== null && response.submitted_at !== null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={lesson.title} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-3xl mx-auto w-full">
                {/* Header */}
                <div className="flex items-start gap-4">
                    <Link href={route('lessons.index')}>
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{lesson.title}</h1>
                        <div className="flex items-center gap-3 mt-2">
                            <Badge variant="secondary">{lesson.subject.name}</Badge>
                            <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                <Calendar className="h-3.5 w-3.5" />
                                {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                                {' às '}
                                {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Lesson description */}
                {lesson.description && (
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Sobre esta aula</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                {lesson.description}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {!lesson.is_available ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <Lock className="h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 mt-4 text-xl font-semibold">Aula ainda não disponível</h3>
                            <p className="text-sm text-muted-foreground max-w-md">
                                O diário reflexivo desta aula estará disponível a partir de{' '}
                                {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long' })}
                                {' às '}
                                {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}.
                            </p>
                        </CardContent>
                    </Card>
                ) : hasResponded && chatMessages.length > 0 ? (
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CheckCircle className="h-5 w-5 text-green-500" />
                                <h2 className="font-semibold">Seu Diário Reflexivo</h2>
                            </div>
                            {response.submitted_at && (
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Clock className="h-3 w-3" />
                                    Enviado em{' '}
                                    {new Date(response.submitted_at).toLocaleDateString('pt-BR', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </span>
                            )}
                        </div>
                        <Card>
                            <CardContent className="pt-4">
                                <ChatHistory messages={chatMessages} showTimestamps={false} />
                            </CardContent>
                        </Card>
                    </div>
                ) : totalQuestions > 0 ? (
                    <ChatDiary
                        lessonId={lesson.id}
                        chatMessages={chatMessages}
                        currentNode={currentNode}
                        totalQuestions={totalQuestions}
                        isCompleted={response !== null && response.submitted_at !== null}
                        draft={draft}
                        turnsRemaining={turnsRemaining}
                        awaitingFinalCheck={awaitingFinalCheck}
                        chatState={chatState ?? 'idle'}
                    />
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <p className="text-sm text-muted-foreground">
                                O roteiro de perguntas ainda não foi configurado. Contate o administrador.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
