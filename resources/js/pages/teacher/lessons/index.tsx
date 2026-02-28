import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
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
import { SearchFilterBar } from '@/components/ui/search-filter-bar';
import type { FilterDefinition } from '@/components/ui/search-filter-bar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { Lesson, Subject } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { BookOpen, Calendar, CalendarPlus, Eye, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    lessons: Lesson[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    filters: {
        subject_id?: string;
    };
}

const breadcrumbs = [{ title: 'Aulas', href: '/teacher/lessons' }];

const DAYS_OF_WEEK = [
    { value: '0', label: 'Domingo' },
    { value: '1', label: 'Segunda-feira' },
    { value: '2', label: 'Terça-feira' },
    { value: '3', label: 'Quarta-feira' },
    { value: '4', label: 'Quinta-feira' },
    { value: '5', label: 'Sexta-feira' },
    { value: '6', label: 'Sábado' },
];

const columns: ColumnDef<Lesson>[] = [
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

// --- Single Lesson Dialog ---

function CreateSingleLessonDialog({ subjects }: { subjects: Pick<Subject, 'id' | 'name'>[] }) {
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

// --- Bulk Lesson Dialog ---

function computeBulkPreview(dayOfWeek: string, startDate: string, endDate: string, startTime: string, titlePrefix: string) {
    if (!dayOfWeek || !startDate || !endDate || !startTime) return [];

    const dow = parseInt(dayOfWeek);
    const start = new Date(startDate + 'T00:00:00');
    const end = new Date(endDate + 'T23:59:59');

    if (isNaN(start.getTime()) || isNaN(end.getTime()) || start > end) return [];

    const current = new Date(start);
    // Advance to first occurrence of the selected day
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

function CreateBulkLessonDialog({ subjects }: { subjects: Pick<Subject, 'id' | 'name'>[] }) {
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
        post(route('teacher.lessons.store-bulk'), {
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
                        {/* Subject */}
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

                        {/* Start date */}
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

// --- Main Page ---

export default function TeacherLessonsIndex({ lessons, subjects }: Props) {
    const [search, setSearch] = useState('');
    const [filterValues, setFilterValues] = useState<Record<string, string[]>>({ subject: [] });

    const subjectFilter: FilterDefinition = useMemo(
        () => ({
            key: 'subject',
            label: 'Matéria',
            options: subjects.map((s) => ({
                value: String(s.id),
                label: s.name,
            })),
        }),
        [subjects],
    );

    const filteredLessons = useMemo(() => {
        let result = lessons;

        if (search.trim()) {
            const q = search.toLowerCase();
            result = result.filter(
                (l) => l.title.toLowerCase().includes(q) || l.subject.name.toLowerCase().includes(q),
            );
        }

        const selectedSubjects = filterValues.subject ?? [];
        if (selectedSubjects.length > 0) {
            result = result.filter((l) => selectedSubjects.includes(String(l.subject.id)));
        }

        return result;
    }, [lessons, search, filterValues]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aulas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Aulas</h1>
                        <p className="text-muted-foreground mt-1">Gerencie os dias de aula das suas matérias</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <CreateSingleLessonDialog subjects={subjects} />
                        <CreateBulkLessonDialog subjects={subjects} />
                    </div>
                </div>

                {/* Search & Filter */}
                <SearchFilterBar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Buscar aulas..."
                    filters={[subjectFilter]}
                    filterValues={filterValues}
                    onFilterChange={(key, values) => setFilterValues((prev) => ({ ...prev, [key]: values }))}
                    onFilterClear={(key, value) =>
                        setFilterValues((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((v) => v !== value) }))
                    }
                    onFilterClearAll={(key) => setFilterValues((prev) => ({ ...prev, [key]: [] }))}
                />

                {/* Table */}
                {filteredLessons.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <BookOpen className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mb-2 mt-4 text-xl font-semibold text-foreground">Nenhuma aula encontrada</h3>
                        <p className="mb-6 max-w-md text-sm text-muted-foreground">
                            {lessons.length === 0
                                ? 'Você ainda não criou nenhuma aula. Use os botões acima para começar.'
                                : 'Nenhuma aula corresponde aos filtros selecionados.'}
                        </p>
                    </div>
                ) : (
                    <div className="hidden md:block">
                        <DataTable columns={columns} data={filteredLessons} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
