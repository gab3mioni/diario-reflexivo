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
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { UnsavedChangesGuard } from '@/components/unsaved-changes-guard';

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

    const { data, setData, put, processing, errors, isDirty } = useForm({
        title: lesson.title,
        description: lesson.description ?? '',
        scheduled_at: formattedDate,
        is_active: lesson.is_active,
    });


    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('lessons.update', lesson.id));
    };

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('lessons.destroy', lesson.id), {
            onFinish: () => {
                setDeleting(false);
                setConfirmOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Aula" />
            <UnsavedChangesGuard dirty={isDirty} />
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

                            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Salvando...' : 'Salvar Alterações'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Danger zone */}
                <Card className="max-w-2xl border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-destructive">Zona de Perigo</CardTitle>
                        <CardDescription>
                            A exclusão é permanente e remove todas as respostas e análises associadas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button type="button" variant="destructive" onClick={() => setConfirmOpen(true)}>
                            Excluir Aula
                        </Button>
                    </CardContent>
                </Card>

                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={setConfirmOpen}
                    tone="destructive"
                    title="Excluir esta aula?"
                    description="Esta ação não pode ser desfeita. Todas as respostas dos alunos e análises geradas por IA serão removidas permanentemente."
                    confirmLabel="Excluir definitivamente"
                    loading={deleting}
                    onConfirm={handleDelete}
                />
            </div>
        </AppLayout>
    );
}
