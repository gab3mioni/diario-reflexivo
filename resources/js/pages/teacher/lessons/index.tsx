import { DataTable } from '@/components/data-table';
import { CreateBulkLessonDialog } from '@/components/teacher/lessons/create-bulk-lesson-dialog';
import { CreateSingleLessonDialog } from '@/components/teacher/lessons/create-single-lesson-dialog';
import { SearchFilterBar } from '@/components/ui/search-filter-bar';
import type { FilterDefinition } from '@/components/ui/search-filter-bar';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import { PageHeader } from '@/components/page-header';
import { EmptyState } from '@/components/empty-state';
import AppLayout from '@/layouts/app-layout';
import { LessonColumns } from '@/types/data-columns/teacher-lessons';
import type { Lesson, Subject } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Calendar, CheckCircle2, ChevronRight, Clock } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    lessons: Lesson[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    filters: {
        subject_id?: string;
    };
}

const breadcrumbs = [{ title: 'Aulas', href: '/lessons' }];

export default function TeacherLessonsIndex({ lessons, subjects }: Props) {
    const [search, setSearch] = useState('');
    const [filterValues, setFilterValues] = useState<Record<string, string[]>>({ subject: [] });

    const subjectFilter: FilterDefinition = useMemo(
        () => ({
            key: 'subject',
            label: 'Matéria',
            options: subjects.map((s) => ({
                value: String(s.id),
                label: s.name,
            })),
        }),
        [subjects],
    );

    const filteredLessons = useMemo(() => {
        let result = lessons;

        if (search.trim()) {
            const q = search.toLowerCase();
            result = result.filter(
                (l) => l.title.toLowerCase().includes(q) || l.subject.name.toLowerCase().includes(q),
            );
        }

        const selectedSubjects = filterValues.subject ?? [];
        if (selectedSubjects.length > 0) {
            result = result.filter((l) => selectedSubjects.includes(String(l.subject.id)));
        }

        return result.sort((a, b) => {
            if (a.is_available === b.is_available) {
                return new Date(b.scheduled_at).getTime() - new Date(a.scheduled_at).getTime();
            }
            return a.is_available ? -1 : 1;
        });
    }, [lessons, search, filterValues]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aulas" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Aulas"
                    description="Gerencie os dias de aula das suas matérias"
                    actions={
                        <>
                            <CreateSingleLessonDialog subjects={subjects} />
                            <CreateBulkLessonDialog subjects={subjects} />
                        </>
                    }
                />

                {/* Search & Filter */}
                <SearchFilterBar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Buscar aulas..."
                    filters={[subjectFilter]}
                    filterValues={filterValues}
                    onFilterChange={(key, values) => setFilterValues((prev) => ({ ...prev, [key]: values }))}
                    onFilterClear={(key, value) =>
                        setFilterValues((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((v) => v !== value) }))
                    }
                    onFilterClearAll={(key) => setFilterValues((prev) => ({ ...prev, [key]: [] }))}
                />

                {/* Table (desktop) / Cards (mobile) */}
                {filteredLessons.length === 0 ? (
                    <EmptyState
                        icon={<BookOpen />}
                        title="Nenhuma aula encontrada"
                        description={
                            lessons.length === 0
                                ? 'Você ainda não criou nenhuma aula. Use os botões acima para começar.'
                                : 'Nenhuma aula corresponde aos filtros selecionados.'
                        }
                    />
                ) : (
                    <>
                        <div className="hidden md:block">
                            <DataTable
                                columns={LessonColumns}
                                data={filteredLessons}
                                sortableColumns={['title', 'subject', 'scheduled_at']}
                            />
                        </div>
                        <ul className="flex flex-col gap-3 md:hidden">
                            {filteredLessons.map((lesson) => {
                                const date = new Date(lesson.scheduled_at);
                                return (
                                    <li key={lesson.id}>
                                        <Link
                                            href={route('lessons.show', lesson.id)}
                                            className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-xl"
                                        >
                                            <Card className="flex flex-row items-center gap-3 p-4 transition-colors hover:bg-muted/40">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="truncate font-medium">{lesson.title}</p>
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        <Badge variant="secondary">{lesson.subject.name}</Badge>
                                                        {lesson.is_available ? (
                                                            <StatusBadge tone="done" size="sm" icon={<CheckCircle2 aria-hidden="true" />}>
                                                                Disponível
                                                            </StatusBadge>
                                                        ) : (
                                                            <StatusBadge tone="locked" size="sm" icon={<Clock aria-hidden="true" />}>
                                                                Agendada
                                                            </StatusBadge>
                                                        )}
                                                    </div>
                                                    <p className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <Calendar className="size-3" aria-hidden="true" />
                                                        {date.toLocaleDateString('pt-BR', {
                                                            day: '2-digit',
                                                            month: '2-digit',
                                                            year: 'numeric',
                                                        })}
                                                        {' · '}
                                                        {lesson.responses_count ?? 0}/{lesson.students_count ?? 0} respostas
                                                    </p>
                                                </div>
                                                <Button variant="ghost" size="icon" tabIndex={-1} className="shrink-0">
                                                    <ChevronRight className="size-4" aria-hidden="true" />
                                                </Button>
                                            </Card>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
