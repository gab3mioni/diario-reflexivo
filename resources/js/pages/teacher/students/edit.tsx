import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

interface Subject {
    id: number;
    name: string;
}

interface StudentData {
    id: number;
    name: string;
    email: string;
    subjects_as_student: Subject[];
}

interface PageProps {
    student: StudentData;
    teacherSubjects: Subject[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Estudantes',
        href: '/students',
    },
    {
        title: 'Editar',
        href: '',
    },
];

export default function TeacherStudentEdit({ student, teacherSubjects }: PageProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: student.name,
        email: student.email,
        subjects: student.subjects_as_student.map(s => s.id),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(route('students.update', student.id));
    }

    function handleSubjectChange(subjectId: number, checked: boolean) {
        if (checked) {
            setData('subjects', [...data.subjects, subjectId]);
        } else {
            setData('subjects', data.subjects.filter(id => id !== subjectId));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar: ${student.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route('students.show', student.id)}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Editar Aluno</h1>
                        <p className="text-muted-foreground">Atualize as informações do aluno</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações Pessoais</CardTitle>
                            <CardDescription>
                                Atualize os dados básicos do aluno
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Matérias</CardTitle>
                            <CardDescription>
                                Selecione as matérias nas quais o aluno está matriculado
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {teacherSubjects.map((subject) => (
                                    <div key={subject.id} className="flex items-center space-x-2">
                                        <Checkbox
                                            id={`subject-${subject.id}`}
                                            checked={data.subjects.includes(subject.id)}
                                            onCheckedChange={(checked) =>
                                                handleSubjectChange(subject.id, checked === true)
                                            }
                                        />
                                        <Label
                                            htmlFor={`subject-${subject.id}`}
                                            className="cursor-pointer"
                                        >
                                            {subject.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            {errors.subjects && (
                                <p className="text-sm text-destructive mt-2">{errors.subjects}</p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-4">
                        <Button variant="outline" asChild>
                            <Link href={route('students.show', student.id)}>
                                Cancelar
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4 mr-2" />
                            Salvar Alterações
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}