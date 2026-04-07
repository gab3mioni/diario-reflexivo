import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { Subject } from '@/types/models';
import { Head, useForm, router } from '@inertiajs/react';

interface LessonEdit {
    id: number;
    title: string;
    description: string | null;
    scheduled_at: string;
    is_active: boolean;
    subject_id: number;
}

interface Props {
    lesson: LessonEdit;
    subjects: Pick<Subject, 'id' | 'name'>[];
}

export default function TeacherLessonsEdit({ lesson, subjects }: Props) {
    const breadcrumbs = [
        { title: 'Aulas', href: '/lessons' },
        { title: 'Editar Aula', href: `/lessons/${lesson.id}/edit` },
    ];

    // Format the ISO date to datetime-local format
    const scheduledDate = new Date(lesson.scheduled_at);
    const formattedDate = scheduledDate.toISOString().slice(0, 16);

    const { data, setData, put, processing, errors } = useForm({
        title: lesson.title,
        description: lesson.description ?? '',
        scheduled_at: formattedDate,
        is_active: lesson.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('lessons.update', lesson.id));
    };

    const handleDelete = () => {
        if (confirm('Tem certeza que deseja excluir esta aula? Esta ação não pode ser desfeita.')) {
            router.delete(route('lessons.destroy', lesson.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Aula" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Editar Aula</h1>
                    <p className="text-muted-foreground mt-1">
                        Atualize as informações da aula
                    </p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Dados da Aula</CardTitle>
                        <CardDescription>
                            Matéria: {subjects.find((s) => s.id === lesson.subject_id)?.name}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                            {/* Title */}
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Ex: Aula 1 - Introdução ao tema"
                                />
                                {errors.title && (
                                    <p className="text-sm text-destructive">{errors.title}</p>
                                )}
                            </div>

                            {/* Description */}
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="description">Descrição (opcional)</Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Descreva o tema ou orientações da aula..."
                                    rows={4}
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}
                            </div>

                            {/* Scheduled At */}
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="scheduled_at">Data e Hora da Aula</Label>
                                <Input
                                    id="scheduled_at"
                                    type="datetime-local"
                                    value={data.scheduled_at}
                                    onChange={(e) => setData('scheduled_at', e.target.value)}
                                />
                                {errors.scheduled_at && (
                                    <p className="text-sm text-destructive">{errors.scheduled_at}</p>
                                )}
                            </div>

                            {/* Active toggle */}
                            <div className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-input"
                                />
                                <Label htmlFor="is_active">Aula ativa (visível para os alunos)</Label>
                            </div>

                            <div className="flex justify-between">
                                <Button type="button" variant="destructive" onClick={handleDelete}>
                                    Excluir Aula
                                </Button>
                                <div className="flex gap-3">
                                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
