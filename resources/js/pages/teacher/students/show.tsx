import { DataTable } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BreadcrumbItem } from '@/types';
import { type Subject } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';

interface StudentData {
    id: number;
    name: string;
    email: string;
    subjects_as_student: Subject[];
}

interface PageProps {
    student: StudentData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Estudantes',
        href: '/teacher/students',
    },
    {
        title: 'Detalhes',
        href: '',
    },
];

export default function TeacherStudentShow({ student }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Aluno: ${student.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route('teacher.students.index')}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{student.name}</h1>
                        <p className="text-muted-foreground">Detalhes do aluno</p>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações Pessoais</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Nome</p>
                                <p className="text-lg">{student.name}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Email</p>
                                <div className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <p className="text-lg">{student.email}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Matérias Matriculadas</CardTitle>
                            <CardDescription>
                                Matérias nas quais este aluno está matriculado com você
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {student.subjects_as_student.map((subject) => (
                                    <Badge key={subject.id} variant="secondary">
                                        {subject.name}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex justify-end">
                    <Button asChild>
                        <Link href={route('teacher.students.edit', student.id)}>
                            Editar Aluno
                        </Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}