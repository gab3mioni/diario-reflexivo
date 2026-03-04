import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Subject } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

export function CreateSingleLessonDialog({ subjects }: { subjects: Pick<Subject, 'id' | 'name'>[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        subject_id: '',
        title: '',
        description: '',
        scheduled_at: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('teacher.lessons.store'), {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus className="h-4 w-4 mr-2" />
                    Aula Única
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Criar Aula</DialogTitle>
                    <DialogDescription>
                        Crie uma aula individual para uma matéria.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="flex flex-col gap-4 mt-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="single-subject">Matéria</Label>
                        <Select value={data.subject_id} onValueChange={(v) => setData('subject_id', v)}>
                            <SelectTrigger id="single-subject">
                                <SelectValue placeholder="Selecione a matéria" />
                            </SelectTrigger>
                            <SelectContent>
                                {subjects.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.subject_id && <p className="text-sm text-destructive">{errors.subject_id}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="single-title">Título</Label>
                        <Input
                            id="single-title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Ex: Aula 1 - Introdução"
                        />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="single-desc">Descrição (opcional)</Label>
                        <textarea
                            id="single-desc"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Tema ou orientações da aula..."
                            rows={3}
                            className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
                        />
                        {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="single-date">Data e Hora</Label>
                        <Input
                            id="single-date"
                            type="datetime-local"
                            value={data.scheduled_at}
                            onChange={(e) => setData('scheduled_at', e.target.value)}
                        />
                        {errors.scheduled_at && <p className="text-sm text-destructive">{errors.scheduled_at}</p>}
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Criando...' : 'Criar Aula'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
