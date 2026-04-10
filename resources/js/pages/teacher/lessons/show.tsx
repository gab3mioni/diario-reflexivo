import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { StatusBadge } from '@/components/status-badge';
import { StatCard } from '@/components/stat-card';
import { PageHeader } from '@/components/page-header';
import { EmptyState } from '@/components/empty-state';
import { AnalysisStatusBadge } from '@/components/analysis-status-badge';
import { AttentionBadge } from '@/components/teacher/attention-badge';
import AppLayout from '@/layouts/app-layout';
import { useEcho } from '@/hooks/use-echo';
import type { Lesson, LessonStudentDetail } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    Bot,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Edit,
    Hourglass,
    Users,
    UserX,
} from 'lucide-react';

const PAGE_SIZE = 10;

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
    const pendingCount = students.length - respondedCount;
    const completion = students.length > 0 ? Math.round((respondedCount / students.length) * 100) : 0;
    const date = new Date(lesson.scheduled_at);

    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;
    useEcho(
        `teacher.${auth.user.id}`,
        '.diary-analysis.updated',
        () => router.reload({ only: ['students'] }),
    );

    const [responsesOpen, setResponsesOpen] = useState(true);
    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(students.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pageStart = (currentPage - 1) * PAGE_SIZE;
    const pagedStudents = students.slice(pageStart, pageStart + PAGE_SIZE);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={lesson.title} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                {/* Back */}
                <Link href={route('lessons.index')} className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Voltar para aulas
                </Link>

                <PageHeader
                    title={lesson.title}
                    description={
                        <span className="flex flex-wrap items-center gap-3">
                            <StatusBadge tone="info">{lesson.subject.name}</StatusBadge>
                            {lesson.is_available ? (
                                <StatusBadge tone="done" icon={<CheckCircle2 aria-hidden="true" />}>
                                    Disponível
                                </StatusBadge>
                            ) : (
                                <StatusBadge tone="locked" icon={<Clock aria-hidden="true" />}>
                                    Agendada
                                </StatusBadge>
                            )}
                            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Calendar className="size-3.5" aria-hidden="true" />
                                {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                                {' às '}
                                {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </span>
                    }
                    actions={
                        <Link href={route('lessons.edit', lesson.id)}>
                            <Button variant="outline">
                                <Edit className="size-4" aria-hidden="true" />
                                Editar
                            </Button>
                        </Link>
                    }
                />

                {lesson.description && (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                {lesson.description}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        label="Total de alunos"
                        value={students.length}
                        icon={<Users />}
                        tone="info"
                    />
                    <StatCard
                        label="Responderam"
                        value={respondedCount}
                        icon={<CheckCircle2 />}
                        tone="done"
                        hint={`${completion}% de conclusão`}
                    />
                    <StatCard
                        label="Pendentes"
                        value={pendingCount}
                        icon={<Hourglass />}
                        tone="pending"
                    />
                </div>

                {/* Progress bar */}
                {students.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Progresso de respostas</span>
                            <span className="tabular-nums">{respondedCount} / {students.length}</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-status-done transition-all"
                                style={{ width: `${completion}%` }}
                                role="progressbar"
                                aria-valuenow={completion}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label={`${completion}% de respostas`}
                            />
                        </div>
                    </div>
                )}

                {/* Student Responses */}
                <Card>
                    <Collapsible open={responsesOpen} onOpenChange={setResponsesOpen}>
                        <CollapsibleTrigger asChild>
                            <CardHeader className="cursor-pointer select-none">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex flex-col gap-1">
                                        <CardTitle>Respostas dos Alunos</CardTitle>
                                        <CardDescription>
                                            Acompanhe quem já respondeu o diário reflexivo desta aula
                                        </CardDescription>
                                    </div>
                                    <ChevronDown
                                        className={`size-5 shrink-0 text-muted-foreground transition-transform ${responsesOpen ? 'rotate-180' : ''}`}
                                        aria-hidden="true"
                                    />
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent>
                        {students.length === 0 ? (
                            <EmptyState
                                compact
                                icon={<UserX />}
                                title="Nenhum aluno matriculado"
                                description="Adicione alunos à matéria desta aula para coletar respostas."
                            />
                        ) : (
                            <>
                            <ul className="flex flex-col divide-y">
                                {pagedStudents.map((student) => (
                                    <li
                                        key={student.id}
                                        className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div
                                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium uppercase text-muted-foreground"
                                                aria-hidden="true"
                                            >
                                                {student.name.slice(0, 2)}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{student.name}</p>
                                                <p className="truncate text-xs text-muted-foreground">{student.email}</p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                            {student.responded ? (
                                                <StatusBadge
                                                    tone="done"
                                                    icon={<CheckCircle2 aria-hidden="true" />}
                                                    size="sm"
                                                >
                                                    Respondido
                                                </StatusBadge>
                                            ) : (
                                                <StatusBadge
                                                    tone="pending"
                                                    icon={<Hourglass aria-hidden="true" />}
                                                    size="sm"
                                                >
                                                    Pendente
                                                </StatusBadge>
                                            )}
                                            {student.response?.latest_analysis_status && (
                                                <AnalysisStatusBadge status={student.response.latest_analysis_status} />
                                            )}
                                            {student.response?.unread_alerts_count ? (
                                                <AttentionBadge
                                                    severity={student.response.highest_alert_severity ?? null}
                                                    count={student.response.unread_alerts_count}
                                                    types={student.response.alert_types}
                                                />
                                            ) : null}
                                            {student.response?.submitted_at && (
                                                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                    <Clock className="size-3" aria-hidden="true" />
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
                                                <Link href={route('diaries.show', student.response.id)}>
                                                    <Button variant="outline" size="sm">
                                                        <Bot className="size-3.5" aria-hidden="true" />
                                                        Ver análise
                                                    </Button>
                                                </Link>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            {totalPages > 1 && (
                                <div className="mt-4 flex items-center justify-between gap-3 border-t pt-4">
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        Mostrando {pageStart + 1}–{Math.min(pageStart + PAGE_SIZE, students.length)} de {students.length}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                                            disabled={currentPage === 1}
                                        >
                                            <ChevronLeft className="size-4" aria-hidden="true" />
                                            Anterior
                                        </Button>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {currentPage} / {totalPages}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                                            disabled={currentPage === totalPages}
                                        >
                                            Próxima
                                            <ChevronRight className="size-4" aria-hidden="true" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                            </>
                        )}
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>
            </div>
        </AppLayout>
    );
}
