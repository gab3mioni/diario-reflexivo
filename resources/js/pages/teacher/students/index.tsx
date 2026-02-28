import { DataTable } from '@/components/data-table';
import { SearchFilterBar } from '@/components/ui/search-filter-bar';
import type { FilterDefinition } from '@/components/ui/search-filter-bar';
import AppLayout from '@/layouts/app-layout';
import { StudentColumns } from '@/types/data-columns/teacher-students';
import { Student } from '@/types/models';
import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs = [
    {
        title: 'Estudantes',
        href: '/teacher/students',
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
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <Users className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mb-2 mt-4 text-xl font-semibold text-foreground">
                            Nenhum estudante encontrado
                        </h3>
                        <p className="mb-6 max-w-md text-sm text-muted-foreground">
                            {hasActiveFilter
                                ? 'Nenhum estudante corresponde aos filtros selecionados.'
                                : 'Você ainda não possui estudantes cadastrados nas suas matérias.'}
                        </p>
                    </div>
                ) : (
                    <div className="hidden md:block">
                        <DataTable columns={StudentColumns} data={filteredStudents} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}