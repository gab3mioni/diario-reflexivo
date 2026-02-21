import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Student } from '@/types/models';
import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Eye, Edit } from 'lucide-react';

export const StudentColumns: ColumnDef<Student>[] = [
    {
        accessorKey: 'name',
        header: 'Nome',
    },
    {
        accessorKey: 'subjects',
        header: 'Matérias',
        cell: ({ row }) => {
            const subjects = row.original.subjects || [];
            if (subjects.length === 0) {
                return <span className="text-muted-foreground">Sem matérias</span>;
            }
            return (
                <div className="flex flex-wrap gap-1">
                    {subjects.map((subject) => (
                        <Badge key={subject.id} variant="secondary">
                            {subject.name}
                        </Badge>
                    ))}
                </div>
            );
        },
    },
    {
        header: 'Ações',
        cell: ({ row }) => (
            <div className="flex gap-2">
                <Link href={route('teacher.students.show', row.original.id)}>
                    <Button variant="ghost" size="sm">
                        <Eye className="h-4 w-4 mr-1" />
                        Visualizar
                    </Button>
                </Link>

                <Link href={route('teacher.students.edit', row.original.id)}>
                    <Button size="sm">
                        <Edit className="h-4 w-4 mr-1" />
                        Editar
                    </Button>
                </Link>
            </div>
        ),
    },
];