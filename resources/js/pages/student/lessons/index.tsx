import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { StudentLesson } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Calendar, CheckCircle, Clock, Lock, MessageSquare } from 'lucide-react';

interface Props {
    pending: StudentLesson[];
    answered: StudentLesson[];
    upcoming: StudentLesson[];
}

const breadcrumbs = [
    { title: 'Minhas Aulas', href: '/student/lessons' },
];

function LessonCard({ lesson, status }: { lesson: StudentLesson; status: 'pending' | 'answered' | 'upcoming' }) {
    const date = new Date(lesson.scheduled_at);

    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <CardTitle className="text-lg">{lesson.title}</CardTitle>
                        <CardDescription className="mt-1">
                            <Badge variant="secondary" className="mr-2">{lesson.subject.name}</Badge>
                            <span className="inline-flex items-center gap-1 text-xs">
                                <Calendar className="h-3 w-3" />
                                {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                                {' às '}
                                {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </CardDescription>
                    </div>
                    {status === 'pending' && (
                        <Badge variant="destructive" className="shrink-0">
                            <Clock className="h-3 w-3 mr-1" />
                            Pendente
                        </Badge>
                    )}
                    {status === 'answered' && (
                        <Badge className="shrink-0 bg-green-600">
                            <CheckCircle className="h-3 w-3 mr-1" />
                            Respondido
                        </Badge>
                    )}
                    {status === 'upcoming' && (
                        <Badge variant="outline" className="shrink-0">
                            <Lock className="h-3 w-3 mr-1" />
                            Agendada
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {lesson.description && (
                    <p className="text-sm text-muted-foreground mb-4 line-clamp-2">
                        {lesson.description}
                    </p>
                )}

                {status === 'pending' && (
                    <Link href={route('student.lessons.show', lesson.id)}>
                        <Button className="w-full">
                            <MessageSquare className="h-4 w-4 mr-2" />
                            Responder Diário
                        </Button>
                    </Link>
                )}

                {status === 'answered' && (
                    <Link href={route('student.lessons.show', lesson.id)}>
                        <Button variant="outline" className="w-full">
                            <CheckCircle className="h-4 w-4 mr-2" />
                            Ver Resposta
                        </Button>
                    </Link>
                )}

                {status === 'upcoming' && (
                    <Button variant="outline" className="w-full" disabled>
                        <Lock className="h-4 w-4 mr-2" />
                        Disponível em{' '}
                        {date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

export default function StudentLessonsIndex({ pending, answered, upcoming }: Props) {
    const hasAny = pending.length > 0 || answered.length > 0 || upcoming.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Minhas Aulas" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Minhas Aulas</h1>
                    <p className="text-muted-foreground mt-1">
                        Visualize suas aulas e responda ao diário reflexivo
                    </p>
                </div>

                {!hasAny ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <BookOpen className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mb-2 mt-4 text-xl font-semibold text-foreground">
                            Nenhuma aula disponível
                        </h3>
                        <p className="mb-6 max-w-md text-sm text-muted-foreground">
                            Seus professores ainda não criaram aulas para as suas matérias.
                        </p>
                    </div>
                ) : (
                    <Accordion type="multiple" defaultValue={['pending']} className="flex flex-col gap-2">
                        {/* Pending - needs attention */}
                        {pending.length > 0 && (
                            <AccordionItem value="pending" className="border rounded-lg px-4">
                                <AccordionTrigger className="text-xl font-semibold hover:no-underline">
                                    <span className="flex items-center gap-2">
                                        <Clock className="h-5 w-5 text-amber-500" />
                                        Pendentes ({pending.length})
                                    </span>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {pending.map((lesson) => (
                                            <LessonCard key={lesson.id} lesson={lesson} status="pending" />
                                        ))}
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        )}

                        {/* Upcoming - locked */}
                        {upcoming.length > 0 && (
                            <AccordionItem value="upcoming" className="border rounded-lg px-4">
                                <AccordionTrigger className="text-xl font-semibold hover:no-underline">
                                    <span className="flex items-center gap-2">
                                        <Lock className="h-5 w-5 text-muted-foreground" />
                                        Próximas Aulas ({upcoming.length})
                                    </span>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {upcoming.map((lesson) => (
                                            <LessonCard key={lesson.id} lesson={lesson} status="upcoming" />
                                        ))}
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        )}

                        {/* Answered */}
                        {answered.length > 0 && (
                            <AccordionItem value="answered" className="border rounded-lg px-4">
                                <AccordionTrigger className="text-xl font-semibold hover:no-underline">
                                    <span className="flex items-center gap-2">
                                        <CheckCircle className="h-5 w-5 text-green-500" />
                                        Respondidas ({answered.length})
                                    </span>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {answered.map((lesson) => (
                                            <LessonCard key={lesson.id} lesson={lesson} status="answered" />
                                        ))}
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        )}
                    </Accordion>
                )}
            </div>
        </AppLayout>
    );
}
