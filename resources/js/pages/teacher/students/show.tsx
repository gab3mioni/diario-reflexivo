import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BreadcrumbItem } from '@/types';
import { type Subject, type StudentLessonForTeacher } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, CheckCircle, Clock, Lock, Mail } from 'lucide-react';

interface StudentData {
    id: number;
    name: string;
    email: string;
    subjects_as_student: Subject[];
}

interface PageProps {
    student: StudentData;
    lessons: {
        pending: StudentLessonForTeacher[];
        answered: StudentLessonForTeacher[];
        upcoming: StudentLessonForTeacher[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Estudantes',
        href: '/students',
    },
    {
        title: 'Detalhes',
        href: '',
    },
];

function LessonItem({ lesson }: { lesson: StudentLessonForTeacher }) {
    const date = new Date(lesson.scheduled_at);
    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1">
                    <p className="font-medium">{lesson.title}</p>
                    <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="secondary" className="text-xs">{lesson.subject.name}</Badge>
                        <span className="inline-flex items-center gap-1">
                            <Calendar className="h-3 w-3" />
                            {date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })}
                            {' às '}
                            {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                        </span>
                    </div>
                </div>
            </div>
            {lesson.response && (
                <div className="mt-3 rounded-md bg-muted/50 p-3">
                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                        Resposta
                        {lesson.response.submitted_at && (
                            <> — {new Date(lesson.response.submitted_at).toLocaleDateString('pt-BR', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}</>
                        )}
                    </p>
                    <p className="whitespace-pre-wrap text-sm">{lesson.response.content}</p>
                </div>
            )}
        </div>
    );
}

export default function TeacherStudentShow({ student, lessons }: PageProps) {
    const totalLessons = lessons.pending.length + lessons.answered.length + lessons.upcoming.length;
    const hasLessons = totalLessons > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Aluno: ${student.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route('students.index')}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{student.name}</h1>
                        <p className="text-muted-foreground">Detalhes do aluno</p>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações Pessoais</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Nome</p>
                                <p className="text-lg">{student.name}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Email</p>
                                <div className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <p className="text-lg">{student.email}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Matérias Matriculadas</CardTitle>
                            <CardDescription>
                                Matérias nas quais este aluno está matriculado com você
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {student.subjects_as_student.map((subject) => (
                                    <Badge key={subject.id} variant="secondary">
                                        {subject.name}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Diary / Lessons Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>Diário Reflexivo</CardTitle>
                        <CardDescription>
                            Acompanhe as respostas e pendências do aluno
                            {hasLessons && (
                                <span className="ml-2">
                                    — {lessons.answered.length} de {lessons.pending.length + lessons.answered.length} respondida{lessons.answered.length !== 1 ? 's' : ''}
                                </span>
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!hasLessons ? (
                            <p className="text-sm text-muted-foreground py-4 text-center">
                                Nenhuma aula disponível para este aluno.
                            </p>
                        ) : (
                            <Accordion type="multiple" defaultValue={['pending']} className="flex flex-col gap-2">
                                {lessons.pending.length > 0 && (
                                    <AccordionItem value="pending" className="border rounded-lg px-4">
                                        <AccordionTrigger className="text-base font-semibold hover:no-underline">
                                            <span className="flex items-center gap-2">
                                                <Clock className="h-4 w-4 text-amber-500" />
                                                Pendentes ({lessons.pending.length})
                                            </span>
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <div className="flex flex-col gap-3">
                                                {lessons.pending.map((lesson) => (
                                                    <LessonItem key={lesson.id} lesson={lesson} />
                                                ))}
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                )}

                                {lessons.upcoming.length > 0 && (
                                    <AccordionItem value="upcoming" className="border rounded-lg px-4">
                                        <AccordionTrigger className="text-base font-semibold hover:no-underline">
                                            <span className="flex items-center gap-2">
                                                <Lock className="h-4 w-4 text-muted-foreground" />
                                                Próximas Aulas ({lessons.upcoming.length})
                                            </span>
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <div className="flex flex-col gap-3">
                                                {lessons.upcoming.map((lesson) => (
                                                    <LessonItem key={lesson.id} lesson={lesson} />
                                                ))}
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                )}

                                {lessons.answered.length > 0 && (
                                    <AccordionItem value="answered" className="border rounded-lg px-4">
                                        <AccordionTrigger className="text-base font-semibold hover:no-underline">
                                            <span className="flex items-center gap-2">
                                                <CheckCircle className="h-4 w-4 text-green-500" />
                                                Respondidas ({lessons.answered.length})
                                            </span>
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <div className="flex flex-col gap-3">
                                                {lessons.answered.map((lesson) => (
                                                    <LessonItem key={lesson.id} lesson={lesson} />
                                                ))}
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                )}
                            </Accordion>
                        )}
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button asChild>
                        <Link href={route('students.edit', student.id)}>
                            Editar Aluno
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}