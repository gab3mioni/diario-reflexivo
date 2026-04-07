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
import { Calendar, CalendarPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

const DAYS_OF_WEEK = [
    { value: '0', label: 'Domingo' },
    { value: '1', label: 'Segunda-feira' },
    { value: '2', label: 'Terça-feira' },
    { value: '3', label: 'Quarta-feira' },
    { value: '4', label: 'Quinta-feira' },
    { value: '5', label: 'Sexta-feira' },
    { value: '6', label: 'Sábado' },
];

function computeBulkPreview(dayOfWeek: string, startDate: string, endDate: string, startTime: string, titlePrefix: string) {
    if (!dayOfWeek || !startDate || !endDate || !startTime) return [];

    const dow = parseInt(dayOfWeek);
    const start = new Date(startDate + 'T00:00:00');
    const end = new Date(endDate + 'T23:59:59');

    if (isNaN(start.getTime()) || isNaN(end.getTime()) || start > end) return [];

    const current = new Date(start);
    while (current.getDay() !== dow && current <= end) {
        current.setDate(current.getDate() + 1);
    }

    const dates: { title: string; date: string }[] = [];
    let counter = 1;

    while (current <= end) {
        dates.push({
            title: `${titlePrefix || 'Aula'} ${counter}`,
            date: current.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) +
                ` às ${startTime}`,
        });
        counter++;
        current.setDate(current.getDate() + 7);
    }

    return dates;
}

export function CreateBulkLessonDialog({ subjects }: { subjects: Pick<Subject, 'id' | 'name'>[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        subject_id: '',
        title_prefix: 'Aula',
        description: '',
        day_of_week: '',
        start_date: '',
        end_date: '',
        start_time: '',
    });

    const preview = useMemo(
        () => computeBulkPreview(data.day_of_week, data.start_date, data.end_date, data.start_time, data.title_prefix),
        [data.day_of_week, data.start_date, data.end_date, data.start_time, data.title_prefix],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('lessons.store-bulk'), {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <CalendarPlus className="h-4 w-4 mr-2" />
                    Criar em Massa
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Criar Aulas em Massa</DialogTitle>
                    <DialogDescription>
                        Gere aulas automaticamente para um dia da semana dentro de um período.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="flex flex-col gap-4 mt-2">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-subject">Matéria</Label>
                            <Select value={data.subject_id} onValueChange={(v) => setData('subject_id', v)}>
                                <SelectTrigger id="bulk-subject">
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

                        {/* Day of week */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-dow">Dia da Semana</Label>
                            <Select value={data.day_of_week} onValueChange={(v) => setData('day_of_week', v)}>
                                <SelectTrigger id="bulk-dow">
                                    <SelectValue placeholder="Selecione o dia" />
                                </SelectTrigger>
                                <SelectContent>
                                    {DAYS_OF_WEEK.map((d) => (
                                        <SelectItem key={d.value} value={d.value}>
                                            {d.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.day_of_week && <p className="text-sm text-destructive">{errors.day_of_week}</p>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-start">Data de Início</Label>
                            <Input
                                id="bulk-start"
                                type="date"
                                value={data.start_date}
                                onChange={(e) => setData('start_date', e.target.value)}
                            />
                            {errors.start_date && <p className="text-sm text-destructive">{errors.start_date}</p>}
                        </div>

                        {/* End date */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-end">Data de Término</Label>
                            <Input
                                id="bulk-end"
                                type="date"
                                value={data.end_date}
                                onChange={(e) => setData('end_date', e.target.value)}
                            />
                            {errors.end_date && <p className="text-sm text-destructive">{errors.end_date}</p>}
                        </div>

                        {/* Start time */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-time">Horário de Início</Label>
                            <Input
                                id="bulk-time"
                                type="time"
                                value={data.start_time}
                                onChange={(e) => setData('start_time', e.target.value)}
                            />
                            {errors.start_time && <p className="text-sm text-destructive">{errors.start_time}</p>}
                        </div>

                        {/* Title prefix */}
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="bulk-prefix">Prefixo do Título</Label>
                            <Input
                                id="bulk-prefix"
                                value={data.title_prefix}
                                onChange={(e) => setData('title_prefix', e.target.value)}
                                placeholder="Ex: Aula"
                            />
                            <p className="text-xs text-muted-foreground">
                                Títulos serão: "{data.title_prefix || 'Aula'} 1", "{data.title_prefix || 'Aula'} 2"...
                            </p>
                            {errors.title_prefix && <p className="text-sm text-destructive">{errors.title_prefix}</p>}
                        </div>
                    </div>

                    {/* Description */}
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="bulk-desc">Descrição (opcional, aplicada a todas)</Label>
                        <textarea
                            id="bulk-desc"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Orientações gerais para todas as aulas..."
                            rows={2}
                            className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
                        />
                        {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                    </div>

                    {/* Preview */}
                    {preview.length > 0 && (
                        <div className="rounded-lg border bg-muted/50 p-4">
                            <h4 className="font-medium mb-2 text-sm">
                                Pré-visualização ({preview.length} aula{preview.length !== 1 ? 's' : ''})
                            </h4>
                            <div className="max-h-40 overflow-y-auto flex flex-col gap-1">
                                {preview.map((item, i) => (
                                    <div key={i} className="flex items-center gap-2 text-sm py-1">
                                        <Calendar className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                        <span className="font-medium">{item.title}</span>
                                        <span className="text-muted-foreground">— {item.date}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing || preview.length === 0}>
                            {processing
                                ? 'Criando...'
                                : `Criar ${preview.length} Aula${preview.length !== 1 ? 's' : ''}`}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
