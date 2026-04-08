import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { UnsavedChangesGuard } from '@/components/unsaved-changes-guard';
import { PageHeader } from '@/components/page-header';

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
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: student.name,
        email: student.email,
        subjects: student.subjects_as_student.map(s => s.id),
    });


    const allSelected = data.subjects.length === teacherSubjects.length;

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
            <UnsavedChangesGuard dirty={isDirty} />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <Link href={route('students.show', student.id)} className="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Voltar ao aluno
                </Link>
                <PageHeader
                    title="Editar Aluno"
                    description="Atualize os dados cadastrais e as matérias do aluno"
                />

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
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>Matérias</CardTitle>
                                <CardDescription>
                                    Selecione as matérias nas quais o aluno está matriculado
                                </CardDescription>
                            </div>
                            {teacherSubjects.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setData(
                                            'subjects',
                                            allSelected ? [] : teacherSubjects.map((s) => s.id),
                                        )
                                    }
                                >
                                    {allSelected ? 'Limpar' : 'Selecionar todas'}
                                </Button>
                            )}
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