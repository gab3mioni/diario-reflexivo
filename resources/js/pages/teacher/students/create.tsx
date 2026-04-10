import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertTriangle, CheckCircle2, Plus, Trash2, Upload, UserPlus } from 'lucide-react';

type Subject = { id: number; name: string };

type BulkPreview = {
    valid: Array<{ line: number; name: string; email: string; subject_id: number }>;
    invalid_format: Array<{ line: number; reason: string }>;
    invalid_subject: Array<{ line: number; email: string; subject_name: string }>;
    duplicate_in_batch: Array<{ line: number; email: string }>;
    email_exists: Array<{ line: number; email: string }>;
};

type PageProps = {
    subjects: Subject[];
    flash?: {
        success?: string;
        preview?: BulkPreview;
        bulk_result?: {
            created_count: number;
            skipped_existing: Array<{ email: string; reason: string }>;
            failed: Array<{ email: string; reason: string }>;
        };
    };
};

type Tab = 'single' | 'rows' | 'csv';

const breadcrumbs = [
    { title: 'Estudantes', href: '/students' },
    { title: 'Criar', href: '/students/create' },
];

export default function Create() {
    const { subjects } = usePage<PageProps>().props;
    const flash = usePage<PageProps>().props.flash ?? {};
    const preview = (flash as any).preview as BulkPreview | undefined;
    const [tab, setTab] = useState<Tab>('single');

    const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
        { key: 'single', label: 'Aluno único', icon: <UserPlus className="size-4" /> },
        { key: 'rows', label: 'Vários (mesma matéria)', icon: <Plus className="size-4" /> },
        { key: 'csv', label: 'CSV / Texto', icon: <Upload className="size-4" /> },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Criar alunos" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Criar alunos"
                    description="Cadastre alunos individualmente ou em lote"
                    icon={<UserPlus />}
                />

                <div className="flex gap-1 border-b">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                tab === t.key
                                    ? 'border-b-2 border-primary text-primary'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.icon}
                            {t.label}
                        </button>
                    ))}
                </div>

                <div className="max-w-2xl">
                    {tab === 'single' && <SingleForm subjects={subjects} />}
                    {tab === 'rows' && <RowsForm subjects={subjects} />}
                    {tab === 'csv' && <CsvForm subjects={subjects} preview={preview} />}
                </div>
            </div>
        </AppLayout>
    );
}

function SingleForm({ subjects }: { subjects: Subject[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        subject_id: subjects[0]?.id ? String(subjects[0].id) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('students.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <Card className="p-6">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Nome completo do aluno"
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="aluno@email.com"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label>Matéria</Label>
                    <Select value={data.subject_id} onValueChange={(v) => setData('subject_id', v)}>
                        <SelectTrigger>
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
                    <InputError message={errors.subject_id} />
                </div>

                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Criar aluno
                </Button>
            </form>
        </Card>
    );
}

type StudentRow = { name: string; email: string };

function RowsForm({ subjects }: { subjects: Subject[] }) {
    const [subjectId, setSubjectId] = useState(subjects[0]?.id ? String(subjects[0].id) : '');
    const [rows, setRows] = useState<StudentRow[]>([{ name: '', email: '' }]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const addRow = () => {
        if (rows.length >= 45) return;
        setRows([...rows, { name: '', email: '' }]);
    };

    const removeRow = (i: number) => {
        if (rows.length <= 1) return;
        setRows(rows.filter((_, idx) => idx !== i));
    };

    const updateRow = (i: number, field: keyof StudentRow, value: string) => {
        const next = [...rows];
        next[i] = { ...next[i], [field]: value };
        setRows(next);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload = rows.map((r) => ({
            ...r,
            subject_id: Number(subjectId),
        }));

        router.post(route('students.bulk.store'), { rows: payload }, {
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Card className="p-6">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label>Matéria</Label>
                    <Select value={subjectId} onValueChange={setSubjectId}>
                        <SelectTrigger>
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
                </div>

                <div className="space-y-2">
                    <Label>Alunos</Label>
                    {rows.map((row, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <div className="flex-1">
                                <Input
                                    placeholder="Nome"
                                    value={row.name}
                                    onChange={(e) => updateRow(i, 'name', e.target.value)}
                                />
                                <InputError message={errors[`rows.${i}.name`]} />
                            </div>
                            <div className="flex-1">
                                <Input
                                    placeholder="E-mail"
                                    type="email"
                                    value={row.email}
                                    onChange={(e) => updateRow(i, 'email', e.target.value)}
                                />
                                <InputError message={errors[`rows.${i}.email`]} />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => removeRow(i)}
                                disabled={rows.length <= 1}
                                className="shrink-0"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>

                <div className="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addRow}
                        disabled={rows.length >= 45}
                    >
                        <Plus className="size-4" />
                        Adicionar linha ({rows.length}/45)
                    </Button>
                </div>

                <InputError message={errors.rows} />

                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Criar {rows.length} {rows.length === 1 ? 'aluno' : 'alunos'}
                </Button>
            </form>
        </Card>
    );
}

function CsvForm({ subjects, preview }: { subjects: Subject[]; preview?: BulkPreview }) {
    const [raw, setRaw] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [previewErrors, setPreviewErrors] = useState<Record<string, string>>({});

    const copyName = (name: string) => {
        navigator.clipboard.writeText(name);
    };

    const previewSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setPreviewErrors({});
        router.post(route('students.bulk.preview'), { raw }, {
            preserveState: true,
            onError: (errs) => setPreviewErrors(errs),
        });
    };

    const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0];
        if (!f) return;
        f.text().then(setRaw);
    };

    const storeValid = () => {
        if (!preview || preview.valid.length === 0) return;
        setSubmitting(true);
        router.post(
            route('students.bulk.store'),
            {
                rows: preview.valid.map((v) => ({
                    name: v.name,
                    email: v.email,
                    subject_id: v.subject_id,
                })),
            },
            { onFinish: () => setSubmitting(false) },
        );
    };

    const totalIssues =
        (preview?.invalid_format.length ?? 0) +
        (preview?.invalid_subject.length ?? 0) +
        (preview?.duplicate_in_batch.length ?? 0) +
        (preview?.email_exists.length ?? 0);

    return (
        <div className="space-y-4">
            <Alert>
                <AlertTriangle className="size-4" />
                <AlertTitle>Instruções</AlertTitle>
                <AlertDescription>
                    <p>
                        As colunas devem ser <code className="rounded bg-muted px-1 font-mono text-xs">nome, email, matéria</code>.
                        O nome da matéria precisa estar <strong>EXATAMENTE</strong> como aparece na sua lista.
                        Clique para copiar:
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {subjects.map((s) => (
                            <button
                                key={s.id}
                                type="button"
                                onClick={() => copyName(s.name)}
                                className="rounded-md border bg-background px-2 py-1 text-xs transition-colors hover:bg-muted"
                            >
                                {s.name}
                            </button>
                        ))}
                    </div>
                </AlertDescription>
            </Alert>

            <Card className="p-6">
                <form onSubmit={previewSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="csv-textarea">Dados CSV / Texto</Label>
                        <textarea
                            id="csv-textarea"
                            rows={10}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-[3px]"
                            placeholder={'nome,email,matéria\nAna Silva,ana@email.com,Matemática'}
                            value={raw}
                            onChange={(e) => setRaw(e.target.value)}
                        />
                        <InputError message={previewErrors.raw} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="csv-file">Ou envie um arquivo CSV</Label>
                        <Input
                            id="csv-file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={handleFileUpload}
                        />
                    </div>

                    <Button type="submit" variant="outline">
                        Pré-visualizar
                    </Button>
                </form>
            </Card>

            {preview && (
                <Card className="p-6 space-y-4">
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <CheckCircle2 className="size-4 text-green-600" />
                        {preview.valid.length} {preview.valid.length === 1 ? 'linha válida' : 'linhas válidas'}
                    </div>

                    {totalIssues > 0 && (
                        <div className="space-y-2">
                            <FeedbackList
                                title="Formato inválido"
                                items={preview.invalid_format.map((r) => `Linha ${r.line}: ${r.reason}`)}
                            />
                            <FeedbackList
                                title="Matéria inválida"
                                items={preview.invalid_subject.map(
                                    (r) => `Linha ${r.line}: ${r.email} — "${r.subject_name}"`,
                                )}
                            />
                            <FeedbackList
                                title="Duplicadas no lote"
                                items={preview.duplicate_in_batch.map(
                                    (r) => `Linha ${r.line}: ${r.email}`,
                                )}
                            />
                            <FeedbackList
                                title="E-mail já cadastrado"
                                items={preview.email_exists.map(
                                    (r) => `Linha ${r.line}: ${r.email}`,
                                )}
                            />
                        </div>
                    )}

                    <Button
                        type="button"
                        onClick={storeValid}
                        disabled={preview.valid.length === 0 || submitting}
                    >
                        {submitting && <Spinner />}
                        Criar {preview.valid.length} {preview.valid.length === 1 ? 'aluno válido' : 'alunos válidos'}
                    </Button>
                </Card>
            )}
        </div>
    );
}

function FeedbackList({ title, items }: { title: string; items: string[] }) {
    if (items.length === 0) return null;
    return (
        <details className="text-sm">
            <summary className="flex cursor-pointer items-center gap-2 font-medium text-amber-600">
                <AlertTriangle className="size-3.5" />
                {title} ({items.length})
            </summary>
            <ul className="mt-1 list-disc space-y-0.5 pl-6 text-muted-foreground">
                {items.map((it, i) => (
                    <li key={i}>{it}</li>
                ))}
            </ul>
        </details>
    );
}
