import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useEcho } from '@/hooks/use-echo';
import type { BreadcrumbItem, Auth } from '@/types';
import { dashboard } from '@/routes';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    BookOpen,
    Bot,
    CheckCircle2,
    Clock,
    FileText,
    Hourglass,
    Layers,
    MessageSquare,
    Sparkles,
    TriangleAlert,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: dashboard().url },
];

type StudentStats = {
    pending_count: number;
    answered_count: number;
    upcoming_count: number;
    total_count: number;
    completion_rate: number;
    subjects_count: number;
    next_lesson: {
        id: number;
        title: string;
        subject: string;
        scheduled_at: string;
        is_available: boolean;
    } | null;
};

type TeacherStats = {
    total_students: number;
    total_lessons: number;
    available_lessons: number;
    total_responses: number;
    pending_review: number;
    analyses_last_7d: number;
    subjects_count: number;
    unread_alerts: number;
    high_severity_alerts: number;
    recent_responses: Array<{
        id: number;
        student_name: string;
        lesson_title: string;
        subject_name: string;
        submitted_at: string;
    }>;
};

type AdminStats = {
    active_provider: { provider: string; model: string } | null;
    active_prompt: { name: string; version: number } | null;
    total_scripts: number;
    active_scripts: number;
    total_users: number;
    total_analyses: number;
    analyses_last_7d: number;
    failed_analyses_last_7d: number;
};

interface DashboardProps {
    dashboardRole: 'student' | 'teacher' | 'admin' | 'guest';
    stats: StudentStats | TeacherStats | AdminStats | null;
}

function greeting(name: string) {
    const hour = new Date().getHours();
    const salut = hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite';
    return `${salut}, ${name.split(' ')[0]}`;
}

function StudentDashboard({ stats, name }: { stats: StudentStats; name: string }) {
    return (
        <>
            <PageHeader
                title={greeting(name)}
                description={
                    stats.pending_count > 0
                        ? `Você tem ${stats.pending_count} ${stats.pending_count === 1 ? 'aula aguardando' : 'aulas aguardando'} sua reflexão.`
                        : 'Você está em dia com seus diários. Parabéns!'
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Pendentes"
                    value={stats.pending_count}
                    icon={<Hourglass />}
                    tone="pending"
                    hint="Aguardando sua resposta"
                />
                <StatCard
                    label="Respondidas"
                    value={stats.answered_count}
                    icon={<CheckCircle2 />}
                    tone="done"
                    hint={`${stats.completion_rate}% de conclusão`}
                />
                <StatCard
                    label="Próximas"
                    value={stats.upcoming_count}
                    icon={<Clock />}
                    tone="info"
                    hint="Agendadas"
                />
                <StatCard
                    label="Matérias"
                    value={stats.subjects_count}
                    icon={<Layers />}
                    tone="default"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Próxima aula</CardTitle>
                        <CardDescription>
                            A aula mais próxima que aguarda sua atenção.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {stats.next_lesson ? (
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <p className="truncate text-lg font-semibold">{stats.next_lesson.title}</p>
                                    <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <StatusBadge tone="info" size="sm">{stats.next_lesson.subject}</StatusBadge>
                                        <span>
                                            {new Date(stats.next_lesson.scheduled_at).toLocaleDateString('pt-BR', {
                                                day: '2-digit',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                            {' · '}
                                            {new Date(stats.next_lesson.scheduled_at).toLocaleTimeString('pt-BR', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                    </p>
                                </div>
                                <Link href={route('lessons.show', stats.next_lesson.id)}>
                                    <Button disabled={!stats.next_lesson.is_available}>
                                        <MessageSquare className="size-4" aria-hidden="true" />
                                        {stats.next_lesson.is_available ? 'Responder' : 'Ver detalhes'}
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <EmptyState
                                compact
                                icon={<BookOpen />}
                                title="Nenhuma aula agendada"
                                description="Você não tem aulas pendentes no momento."
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Seu progresso</CardTitle>
                        <CardDescription>No total de aulas disponíveis</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-end gap-1.5">
                            <span className="text-5xl font-bold tabular-nums leading-none">
                                {stats.completion_rate}
                            </span>
                            <span className="pb-1 text-xl text-muted-foreground">%</span>
                        </div>
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-status-done transition-all"
                                style={{ width: `${stats.completion_rate}%` }}
                            />
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            {stats.answered_count} de {stats.total_count} aulas respondidas
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="flex justify-end">
                <Link href={route('lessons.index')}>
                    <Button variant="outline">
                        Ver todas as aulas
                    </Button>
                </Link>
            </div>
        </>
    );
}

function TeacherDashboardRealtime({ teacherId }: { teacherId: number }) {
    useEcho(
        `teacher.${teacherId}`,
        '.lesson-response.submitted',
        () => router.reload({ only: ['stats'] }),
    );
    useEcho(
        `teacher.${teacherId}`,
        '.diary-analysis.updated',
        () => router.reload({ only: ['stats'] }),
    );
    return null;
}

function TeacherDashboard({ stats, name }: { stats: TeacherStats; name: string }) {
    return (
        <>
            <PageHeader
                title={greeting(name)}
                description={
                    stats.pending_review > 0
                        ? `Você tem ${stats.pending_review} ${stats.pending_review === 1 ? 'análise aguardando revisão' : 'análises aguardando revisão'}.`
                        : 'Visão geral das suas matérias, aulas e análises.'
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Alunos"
                    value={stats.total_students}
                    icon={<Users />}
                    tone="info"
                    hint={`Em ${stats.subjects_count} ${stats.subjects_count === 1 ? 'matéria' : 'matérias'}`}
                />
                <StatCard
                    label="Aulas disponíveis"
                    value={stats.available_lessons}
                    icon={<BookOpen />}
                    tone="done"
                    hint={`De ${stats.total_lessons} no total`}
                />
                <StatCard
                    label="Respostas totais"
                    value={stats.total_responses}
                    icon={<MessageSquare />}
                    tone="default"
                />
                <StatCard
                    label="Aguardam revisão"
                    value={stats.pending_review}
                    icon={<Bot />}
                    tone={stats.pending_review > 0 ? 'pending' : 'default'}
                    hint={`${stats.analyses_last_7d} análises nos últimos 7 dias`}
                />
            </div>

            {stats.unread_alerts > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Alertas não lidos"
                        value={stats.unread_alerts}
                        icon={<TriangleAlert />}
                        tone="pending"
                        hint={
                            stats.high_severity_alerts > 0
                                ? `${stats.high_severity_alerts} de severidade alta`
                                : 'Verifique nas aulas'
                        }
                    />
                </div>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Respostas recentes</CardTitle>
                    <CardDescription>Últimas submissões dos seus alunos</CardDescription>
                </CardHeader>
                <CardContent>
                    {stats.recent_responses.length === 0 ? (
                        <EmptyState
                            compact
                            icon={<MessageSquare />}
                            title="Nenhuma resposta ainda"
                            description="Assim que seus alunos responderem aos diários, eles aparecerão aqui."
                        />
                    ) : (
                        <ul className="flex flex-col divide-y">
                            {stats.recent_responses.map((r) => (
                                <li key={r.id} className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.student_name}</p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {r.lesson_title} · {r.subject_name}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-muted-foreground">
                                            {new Date(r.submitted_at).toLocaleDateString('pt-BR', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                        <Link href={route('diaries.show', r.id)}>
                                            <Button variant="ghost" size="sm">Ver</Button>
                                        </Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

function AdminDashboard({ stats, name }: { stats: AdminStats; name: string }) {
    return (
        <>
            <PageHeader
                title={greeting(name)}
                description="Saúde do sistema, análises de IA e configurações globais."
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Análises totais"
                    value={stats.total_analyses}
                    icon={<Sparkles />}
                    tone="info"
                    hint={`${stats.analyses_last_7d} nos últimos 7 dias`}
                />
                <StatCard
                    label="Falhas recentes"
                    value={stats.failed_analyses_last_7d}
                    icon={<TriangleAlert />}
                    tone={stats.failed_analyses_last_7d > 0 ? 'pending' : 'done'}
                    hint="Nos últimos 7 dias"
                />
                <StatCard
                    label="Roteiros ativos"
                    value={stats.active_scripts}
                    icon={<FileText />}
                    tone="done"
                    hint={`De ${stats.total_scripts} no total`}
                />
                <StatCard
                    label="Usuários"
                    value={stats.total_users}
                    icon={<Users />}
                    tone="default"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Bot className="size-5 text-muted-foreground" />
                            Provedor de IA ativo
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.active_provider ? (
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center gap-2">
                                    <StatusBadge tone="done" dot pulse>
                                        {stats.active_provider.provider}
                                    </StatusBadge>
                                </div>
                                <p className="font-mono text-sm text-muted-foreground">
                                    {stats.active_provider.model}
                                </p>
                                <Link href={route('ai-config.index')} className="mt-2">
                                    <Button variant="outline" size="sm">
                                        Configurar
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <EmptyState
                                compact
                                icon={<TriangleAlert />}
                                title="Nenhum provedor configurado"
                                description="Configure um provedor de IA para habilitar análises."
                                action={
                                    <Link href={route('ai-config.index')}>
                                        <Button size="sm">Configurar agora</Button>
                                    </Link>
                                }
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="size-5 text-muted-foreground" />
                            Prompt de análise
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {stats.active_prompt ? (
                            <div className="flex flex-col gap-2">
                                <p className="font-medium">{stats.active_prompt.name}</p>
                                <StatusBadge tone="info" size="sm">
                                    v{stats.active_prompt.version}
                                </StatusBadge>
                                <Link href={route('ai-config.index')} className="mt-2">
                                    <Button variant="outline" size="sm">
                                        Editar prompt
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <EmptyState
                                compact
                                title="Nenhum prompt definido"
                                description="Crie um prompt para guiar a IA nas análises."
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

export default function Dashboard({ dashboardRole, stats }: DashboardProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const name = auth.user?.name ?? 'usuário';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Painel" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                {dashboardRole === 'student' && stats && (
                    <StudentDashboard stats={stats as StudentStats} name={name} />
                )}
                {dashboardRole === 'teacher' && stats && (
                    <>
                        {auth.user && <TeacherDashboardRealtime teacherId={auth.user.id} />}
                        <TeacherDashboard stats={stats as TeacherStats} name={name} />
                    </>
                )}
                {dashboardRole === 'admin' && stats && (
                    <AdminDashboard stats={stats as AdminStats} name={name} />
                )}
                {(!stats || dashboardRole === 'guest') && (
                    <EmptyState
                        icon={<Sparkles />}
                        title={greeting(name)}
                        description="Selecione um papel no menu para começar."
                    />
                )}
            </div>
        </AppLayout>
    );
}
