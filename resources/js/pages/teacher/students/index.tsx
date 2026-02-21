import { DataTable } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import { StudentColumns } from '@/types/data-columns/teacher-students';
import { Student } from '@/types/models';
import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';

const breadcrumbs = [
    {
        title: 'Estudantes',
        href: '/teacher/students',
    },
];

export default function StudentsList({ students }: { students: Student[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estudantes" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {students.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <Users className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mb-2 mt-4 text-xl font-semibold text-foreground">
                            Nenhum estudante encontrado
                        </h3>
                        <p className="mb-6 max-w-md text-sm text-muted-foreground">
                            Você ainda não possui estudantes cadastrados nas suas matérias.
                        </p>
                    </div>
                ) : (
                    <div className="hidden md:block">
                        <DataTable columns={StudentColumns} data={students} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}