import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Lesson } from '@/types/models';
import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Calendar, Eye } from 'lucide-react';

export const LessonColumns: ColumnDef<Lesson>[] = [
    {
        accessorKey: 'title',
        header: 'Título',
        cell: ({ row }) => (
            <div>
                <p className="font-medium">{row.original.title}</p>
                {row.original.description && (
                    <p className="text-xs text-muted-foreground line-clamp-1">{row.original.description}</p>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'subject',
        header: 'Matéria',
        cell: ({ row }) => <Badge variant="secondary">{row.original.subject.name}</Badge>,
    },
    {
        accessorKey: 'scheduled_at',
        header: 'Data da Aula',
        cell: ({ row }) => {
            const date = new Date(row.original.scheduled_at);
            return (
                <div className="flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                    <span>{date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })}</span>
                    <span className="text-muted-foreground">
                        {date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                    </span>
                </div>
            );
        },
    },
    {
        header: 'Respostas',
        cell: ({ row }) => (
            <span className="text-sm">
                {row.original.responses_count ?? 0} / {row.original.students_count ?? 0}
            </span>
        ),
    },
    {
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={row.original.is_available ? 'default' : 'outline'}>
                {row.original.is_available ? 'Disponível' : 'Agendada'}
            </Badge>
        ),
    },
    {
        header: 'Ações',
        cell: ({ row }) => (
            <Link href={route('teacher.lessons.show', row.original.id)}>
                <Button variant="ghost" size="sm">
                    <Eye className="h-4 w-4 mr-1" />
                    Ver
                </Button>
            </Link>
        ),
    },
];
