import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { Lesson, LessonStudentDetail } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Bot, Calendar, CheckCircle, Clock, Edit, XCircle } from 'lucide-react';

interface Props {
    lesson: Lesson;
    students: LessonStudentDetail[];
}

export default function TeacherLessonsShow({ lesson, students }: Props) {
    const breadcrumbs = [
        { title: 'Aulas', href: '/lessons' },
        { title: lesson.title, href: `/lessons/${lesson.id}` },
    ];

    const respondedCount = students.filter((s) => s.responded).length;
    const date = new Date(lesson.scheduled_at);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={lesson.title} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Header */}
                <div className="flex items-start justify-between">
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
                                <Badge variant={lesson.is_available ? 'default' : 'outline'}>
                                    {lesson.is_available ? 'Disponível' : 'Agendada'}
                                </Badge>
                                <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <Calendar className="h-3.5 w-3.5" />
                                    {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                                    {' às '}
                                    {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                </span>
                            </div>
                            {lesson.description && (
                                <p className="mt-3 text-sm text-muted-foreground max-w-2xl">
                                    {lesson.description}
                                </p>
                            )}
                        </div>
                    </div>
                    <Link href={route('lessons.edit', lesson.id)}>
                        <Button variant="outline">
                            <Edit className="h-4 w-4 mr-2" />
                            Editar
                        </Button>
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total de Alunos</CardDescription>
                            <CardTitle className="text-2xl">{students.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Responderam</CardDescription>
                            <CardTitle className="text-2xl text-green-600">{respondedCount}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Pendentes</CardDescription>
                            <CardTitle className="text-2xl text-amber-600">
                                {students.length - respondedCount}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {/* Student Responses */}
                <Card>
                    <CardHeader>
                        <CardTitle>Respostas dos Alunos</CardTitle>
                        <CardDescription>
                            Acompanhe quem já respondeu o diário reflexivo desta aula
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col divide-y">
                            {students.map((student) => (
                                <div key={student.id} className="flex items-center justify-between py-4 first:pt-0 last:pb-0">
                                    <div className="flex items-center gap-3">
                                        <div>
                                            {student.responded ? (
                                                <CheckCircle className="h-5 w-5 text-green-500" />
                                            ) : (
                                                <XCircle className="h-5 w-5 text-muted-foreground" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="font-medium">{student.name}</p>
                                            <p className="text-sm text-muted-foreground">{student.email}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {student.response?.submitted_at && (
                                            <span className="flex items-center gap-1 text-xs text-muted-foreground mr-2">
                                                <Clock className="h-3 w-3" />
                                                {new Date(student.response.submitted_at).toLocaleDateString('pt-BR', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        )}
                                        {student.responded && student.response && (
                                            <Link
                                                href={route('diaries.show', student.response.id)}
                                            >
                                                <Button variant="outline" size="sm">
                                                    <Bot className="h-3.5 w-3.5 mr-1.5" />
                                                    Ver Análise
                                                    {student.response.latest_analysis_status && (
                                                        <Badge
                                                            variant={
                                                                student.response.latest_analysis_status === 'approved' ? 'default' :
                                                                student.response.latest_analysis_status === 'rejected' ? 'destructive' :
                                                                student.response.latest_analysis_status === 'completed' ? 'outline' :
                                                                'secondary'
                                                            }
                                                            className="ml-1.5"
                                                        >
                                                            {student.response.latest_analysis_status === 'approved' ? 'Aprovada' :
                                                             student.response.latest_analysis_status === 'rejected' ? 'Rejeitada' :
                                                             student.response.latest_analysis_status === 'completed' ? 'Pendente revisão' :
                                                             student.response.latest_analysis_status === 'pending' ? 'Processando' :
                                                             student.response.latest_analysis_status === 'failed' ? 'Falhou' : ''}
                                                        </Badge>
                                                    )}
                                                </Button>
                                            </Link>
                                        )}
                                        <Badge variant={student.responded ? 'default' : 'outline'}>
                                            {student.responded ? 'Respondido' : 'Pendente'}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
