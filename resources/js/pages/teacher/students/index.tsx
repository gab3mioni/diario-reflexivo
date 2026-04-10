import { DataTable } from '@/components/data-table';
import { SearchFilterBar } from '@/components/ui/search-filter-bar';
import type { FilterDefinition } from '@/components/ui/search-filter-bar';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/page-header';
import { EmptyState } from '@/components/empty-state';
import AppLayout from '@/layouts/app-layout';
import { StudentColumns } from '@/types/data-columns/teacher-students';
import { Student } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs = [
    {
        title: 'Estudantes',
        href: '/students',
    },
];

export default function StudentsList({ students }: { students: Student[] }) {
    const [search, setSearch] = useState('');
    const [filterValues, setFilterValues] = useState<Record<string, string[]>>({ subject: [] });

    const subjectFilter: FilterDefinition = useMemo(() => {
        const uniqueSubjects = new Map<number, string>();
        students.forEach((s) =>
            s.subjects?.forEach((sub) => uniqueSubjects.set(sub.id, sub.name)),
        );

        return {
            key: 'subject',
            label: 'Filtrar por matéria',
            options: Array.from(uniqueSubjects.entries()).map(([id, name]) => ({
                value: String(id),
                label: name,
            })),
        };
    }, [students]);

    const filteredStudents = useMemo(() => {
        let result = students;

        if (search.trim()) {
            const q = search.toLowerCase();
            result = result.filter(
                (s) =>
                    s.name.toLowerCase().includes(q) ||
                    s.email.toLowerCase().includes(q),
            );
        }

        const selectedSubjects = filterValues.subject ?? [];
        if (selectedSubjects.length > 0) {
            result = result.filter((s) =>
                s.subjects?.some((sub) => selectedSubjects.includes(String(sub.id))),
            );
        }

        return result;
    }, [students, search, filterValues]);

    const handleFilterChange = (key: string, values: string[]) => {
        setFilterValues((prev) => ({ ...prev, [key]: values }));
    };

    const handleFilterClear = (key: string, value: string) => {
        setFilterValues((prev) => ({
            ...prev,
            [key]: (prev[key] ?? []).filter((v) => v !== value),
        }));
    };

    const handleFilterClearAll = (key: string) => {
        setFilterValues((prev) => ({ ...prev, [key]: [] }));
    };

    const hasActiveFilter = search.trim() !== '' || (filterValues.subject?.length ?? 0) > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estudantes" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Estudantes"
                    description={
                        students.length > 0
                            ? `${students.length} ${students.length === 1 ? 'aluno matriculado' : 'alunos matriculados'} nas suas matérias`
                            : 'Alunos matriculados nas suas matérias aparecerão aqui'
                    }
                    actions={
                        <Link href={route('students.create')}>
                            <Button>
                                <Users className="size-4" />
                                Criar aluno
                            </Button>
                        </Link>
                    }
                />

                {students.length > 0 && (
                    <SearchFilterBar
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Buscar estudantes..."
                        filters={[subjectFilter]}
                        filterValues={filterValues}
                        onFilterChange={handleFilterChange}
                        onFilterClear={handleFilterClear}
                        onFilterClearAll={handleFilterClearAll}
                    />
                )}

                {filteredStudents.length === 0 ? (
                    <EmptyState
                        icon={<Users />}
                        title="Nenhum estudante encontrado"
                        description={
                            hasActiveFilter
                                ? 'Nenhum estudante corresponde aos filtros selecionados.'
                                : 'Você ainda não possui estudantes cadastrados nas suas matérias.'
                        }
                    />
                ) : (
                    <>
                        <div className="hidden md:block">
                            <DataTable columns={StudentColumns} data={filteredStudents} sortableColumns={['name']} />
                        </div>
                        <ul className="flex flex-col gap-3 md:hidden">
                            {filteredStudents.map((student) => (
                                <li key={student.id}>
                                    <Link
                                        href={route('students.show', student.id)}
                                        className="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <Card className="flex flex-row items-center gap-3 p-4 transition-colors hover:bg-muted/40">
                                            <div
                                                className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium uppercase text-muted-foreground"
                                                aria-hidden="true"
                                            >
                                                {student.name.slice(0, 2)}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">{student.name}</p>
                                                <p className="truncate text-xs text-muted-foreground">{student.email}</p>
                                                {student.subjects && student.subjects.length > 0 && (
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        {student.subjects.slice(0, 3).map((sub) => (
                                                            <Badge key={sub.id} variant="secondary" className="text-[10px]">
                                                                {sub.name}
                                                            </Badge>
                                                        ))}
                                                        {student.subjects.length > 3 && (
                                                            <Badge variant="outline" className="text-[10px]">
                                                                +{student.subjects.length - 3}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                            <Button variant="ghost" size="icon" tabIndex={-1} className="shrink-0">
                                                <ChevronRight className="size-4" aria-hidden="true" />
                                            </Button>
                                        </Card>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </AppLayout>
    );
}