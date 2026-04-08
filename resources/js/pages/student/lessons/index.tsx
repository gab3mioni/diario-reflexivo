import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/status-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { StudentLesson } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Calendar, CheckCircle, Clock, Lock, MessageSquare, Pencil } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
    pending: StudentLesson[];
    inProgress: StudentLesson[];
    answered: StudentLesson[];
    upcoming: StudentLesson[];
}

const breadcrumbs = [
    { title: 'Minhas Aulas', href: '/lessons' },
];

type LessonStatus = 'pending' | 'in_progress' | 'answered' | 'upcoming';

const statusConfig: Record<
    LessonStatus,
    {
        label: string;
        tone: 'pending' | 'progress' | 'done' | 'locked';
        icon: React.ComponentType<{ className?: string }>;
        accent: string;
    }
> = {
    pending: { label: 'Pendente', tone: 'pending', icon: Clock, accent: 'border-l-status-pending' },
    in_progress: { label: 'Em andamento', tone: 'progress', icon: Pencil, accent: 'border-l-status-progress' },
    answered: { label: 'Respondido', tone: 'done', icon: CheckCircle, accent: 'border-l-status-done' },
    upcoming: { label: 'Agendada', tone: 'locked', icon: Lock, accent: 'border-l-status-locked' },
};

function LessonCard({ lesson, status }: { lesson: StudentLesson; status: LessonStatus }) {
    const date = new Date(lesson.scheduled_at);
    const cfg = statusConfig[status];
    const Icon = cfg.icon;

    return (
        <Card
            className={cn(
                'group relative overflow-hidden border-l-4 transition-all hover:-translate-y-0.5 hover:shadow-md focus-within:ring-2 focus-within:ring-ring/50',
                cfg.accent,
            )}
        >
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <CardTitle className="text-lg leading-tight">{lesson.title}</CardTitle>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="secondary">{lesson.subject.name}</Badge>
                            <span className="inline-flex items-center gap-1">
                                <Calendar className="size-3" aria-hidden="true" />
                                {date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long' })}
                                {' às '}
                                {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </div>
                    </div>
                    <StatusBadge tone={cfg.tone} icon={<Icon aria-hidden="true" />} className="shrink-0">
                        {cfg.label}
                    </StatusBadge>
                </div>
            </CardHeader>
            <CardContent>
                {lesson.description && (
                    <p className="mb-4 line-clamp-2 text-sm text-muted-foreground">
                        {lesson.description}
                    </p>
                )}

                {status === 'pending' && (
                    <Link href={route('lessons.show', lesson.id)}>
                        <Button className="w-full">
                            <MessageSquare className="size-4" aria-hidden="true" />
                            Responder Diário
                        </Button>
                    </Link>
                )}

                {status === 'in_progress' && (
                    <Link href={route('lessons.show', lesson.id)}>
                        <Button className="w-full" variant="default">
                            <Pencil className="size-4" aria-hidden="true" />
                            Continuar Diário
                        </Button>
                    </Link>
                )}

                {status === 'answered' && (
                    <Link href={route('lessons.show', lesson.id)}>
                        <Button variant="outline" className="w-full">
                            <CheckCircle className="size-4" aria-hidden="true" />
                            Ver Resposta
                        </Button>
                    </Link>
                )}

                {status === 'upcoming' && (
                    <Button variant="outline" className="w-full" disabled>
                        <Lock className="size-4" aria-hidden="true" />
                        Disponível em {date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

interface SectionProps {
    value: string;
    title: string;
    count: number;
    icon: React.ComponentType<{ className?: string }>;
    iconClass: string;
    lessons: StudentLesson[];
    status: LessonStatus;
}

function LessonSection({ value, title, count, icon: Icon, iconClass, lessons, status }: SectionProps) {
    if (count === 0) return null;
    return (
        <AccordionItem value={value} className="rounded-xl border bg-card px-4 shadow-xs">
            <AccordionTrigger className="text-lg font-semibold hover:no-underline">
                <span className="flex items-center gap-2.5">
                    <Icon className={cn('size-5', iconClass)} aria-hidden="true" />
                    {title}
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        {count}
                    </span>
                </span>
            </AccordionTrigger>
            <AccordionContent>
                <div className="grid gap-4 pb-2 sm:grid-cols-2 lg:grid-cols-3">
                    {lessons.map((lesson) => (
                        <LessonCard key={lesson.id} lesson={lesson} status={status} />
                    ))}
                </div>
            </AccordionContent>
        </AccordionItem>
    );
}

export default function StudentLessonsIndex({ pending, inProgress, answered, upcoming }: Props) {
    const hasAny = pending.length + inProgress.length + answered.length + upcoming.length > 0;
    const activeCount = pending.length + inProgress.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Minhas Aulas" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Minhas Aulas"
                    description={
                        activeCount > 0
                            ? `Você tem ${activeCount} ${activeCount === 1 ? 'aula aguardando' : 'aulas aguardando'} sua reflexão.`
                            : 'Acompanhe suas aulas e responda ao diário reflexivo.'
                    }
                />

                {!hasAny ? (
                    <EmptyState
                        icon={<BookOpen />}
                        title="Nenhuma aula disponível"
                        description="Seus professores ainda não criaram aulas para as suas matérias. Volte em breve."
                    />
                ) : (
                    <Accordion
                        type="multiple"
                        defaultValue={[pending.length > 0 ? 'pending' : 'in_progress']}
                        className="flex flex-col gap-3"
                    >
                        <LessonSection
                            value="pending"
                            title="Pendentes"
                            count={pending.length}
                            icon={Clock}
                            iconClass="text-status-pending"
                            lessons={pending}
                            status="pending"
                        />
                        <LessonSection
                            value="in_progress"
                            title="Em Andamento"
                            count={inProgress.length}
                            icon={Pencil}
                            iconClass="text-status-progress"
                            lessons={inProgress}
                            status="in_progress"
                        />
                        <LessonSection
                            value="upcoming"
                            title="Próximas Aulas"
                            count={upcoming.length}
                            icon={Lock}
                            iconClass="text-muted-foreground"
                            lessons={upcoming}
                            status="upcoming"
                        />
                        <LessonSection
                            value="answered"
                            title="Respondidas"
                            count={answered.length}
                            icon={CheckCircle}
                            iconClass="text-status-done"
                            lessons={answered}
                            status="answered"
                        />
                    </Accordion>
                )}
            </div>
        </AppLayout>
    );
}
