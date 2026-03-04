import { DataTable } from '@/components/data-table';
import { CreateBulkLessonDialog } from '@/components/teacher/lessons/create-bulk-lesson-dialog';
import { CreateSingleLessonDialog } from '@/components/teacher/lessons/create-single-lesson-dialog';
import { SearchFilterBar } from '@/components/ui/search-filter-bar';
import type { FilterDefinition } from '@/components/ui/search-filter-bar';
import AppLayout from '@/layouts/app-layout';
import { LessonColumns } from '@/types/data-columns/teacher-lessons';
import type { Lesson, Subject } from '@/types/models';
import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    lessons: Lesson[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    filters: {
        subject_id?: string;
    };
}

const breadcrumbs = [{ title: 'Aulas', href: '/teacher/lessons' }];

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
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Aulas</h1>
                        <p className="text-muted-foreground mt-1">Gerencie os dias de aula das suas matérias</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <CreateSingleLessonDialog subjects={subjects} />
                        <CreateBulkLessonDialog subjects={subjects} />
                    </div>
                </div>

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

                {/* Table */}
                {filteredLessons.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <BookOpen className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mb-2 mt-4 text-xl font-semibold text-foreground">Nenhuma aula encontrada</h3>
                        <p className="mb-6 max-w-md text-sm text-muted-foreground">
                            {lessons.length === 0
                                ? 'Você ainda não criou nenhuma aula. Use os botões acima para começar.'
                                : 'Nenhuma aula corresponde aos filtros selecionados.'}
                        </p>
                    </div>
                ) : (
                    <div className="hidden md:block">
                        <DataTable columns={LessonColumns} data={filteredLessons} sortableColumns={['title', 'subject', 'scheduled_at']} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
