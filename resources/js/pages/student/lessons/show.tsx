import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { Lesson, LessonResponse } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, CheckCircle, Clock, Lock, Send } from 'lucide-react';
import { useState } from 'react';

interface Props {
    lesson: Lesson;
    response: LessonResponse | null;
}

export default function StudentLessonShow({ lesson, response }: Props) {
    const breadcrumbs = [
        { title: 'Minhas Aulas', href: '/student/lessons' },
        { title: lesson.title, href: `/student/lessons/${lesson.id}` },
    ];

    const date = new Date(lesson.scheduled_at);
    const hasResponded = response !== null;

    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        content: response?.content ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setConfirmOpen(true);
    };

    const handleConfirmSubmit = () => {
        setConfirmOpen(false);
        post(route('student.lessons.respond', lesson.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={lesson.title} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-3xl mx-auto w-full">
                {/* Header */}
                <div className="flex items-start gap-4">
                    <Link href={route('student.lessons.index')}>
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

                {/* Diary form or response view */}
                {!lesson.is_available ? (
                    // Lesson is in the future - locked
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
                ) : hasResponded ? (
                    // Already responded - show response (read-only)
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle className="h-5 w-5 text-green-500" />
                                    Seu Diário Reflexivo
                                </CardTitle>
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
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-lg bg-muted/50 p-4">
                                <p className="whitespace-pre-wrap text-sm">{response.content}</p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    // Available but not yet responded - show form
                    <Card>
                        <CardHeader>
                            <CardTitle>Diário Reflexivo</CardTitle>
                            <CardDescription>
                                Escreva sua reflexão sobre o conteúdo abordado nesta aula.
                                Reflita sobre o que aprendeu, dúvidas que surgiram e como você
                                pode aplicar o conhecimento.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                                <textarea
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    rows={8}
                                    placeholder="Escreva sua reflexão sobre a aula de hoje..."
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {errors.content && (
                                    <p className="text-sm text-destructive">{errors.content}</p>
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        <Send className="h-4 w-4 mr-2" />
                                        {processing ? 'Enviando...' : 'Enviar Diário'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Confirmation dialog */}
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirmar envio</DialogTitle>
                            <DialogDescription>
                                Tem certeza que deseja enviar sua resposta? Após o envio, não será possível alterá-la.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                                Cancelar
                            </Button>
                            <Button onClick={handleConfirmSubmit} disabled={processing}>
                                <Send className="h-4 w-4 mr-2" />
                                {processing ? 'Enviando...' : 'Confirmar Envio'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
