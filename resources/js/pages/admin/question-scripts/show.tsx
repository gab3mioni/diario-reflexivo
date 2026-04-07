import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    CheckCircle,
    MessageCircle,
    Pencil,
    Play,
    Plus,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';

interface ScriptDetail {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
    ordered_nodes: QuestionScriptNode[];
    created_at: string;
    updated_at: string;
}

interface Props {
    script: ScriptDetail;
}

export default function AdminQuestionScriptShow({ script }: Props) {
    const breadcrumbs = [
        { title: 'Roteiros de Perguntas', href: '/question-scripts' },
        { title: script.name, href: `/question-scripts/${script.id}` },
    ];

    const [isEditing, setIsEditing] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: script.name,
        description: script.description ?? '',
        nodes: script.nodes as QuestionScriptNode[],
        edges: script.edges as QuestionScriptEdge[],
    });

    const orderedNodes = getOrderedNodes(data.nodes, data.edges);
    const questionNodes = orderedNodes.filter((n) => n.type === 'question');
    const startNode = orderedNodes.find((n) => n.type === 'start');
    const endNode = orderedNodes.find((n) => n.type === 'end');

    function getOrderedNodes(nodes: QuestionScriptNode[], edges: QuestionScriptEdge[]): QuestionScriptNode[] {
        const nodeMap = new Map(nodes.map((n) => [n.id, n]));
        const start = nodes.find((n) => n.type === 'start');
        if (!start) return [];

        const ordered: QuestionScriptNode[] = [];
        let currentId: string | null = start.id;
        const visited = new Set<string>();

        while (currentId && !visited.has(currentId)) {
            visited.add(currentId);
            const node = nodeMap.get(currentId);
            if (node) ordered.push(node);
            const edge = edges.find((e) => e.source === currentId);
            currentId = edge?.target ?? null;
        }

        return ordered;
    }

    const updateNodeMessage = (nodeId: string, message: string) => {
        setData(
            'nodes',
            data.nodes.map((n) => (n.id === nodeId ? { ...n, data: { ...n.data, message } } : n)),
        );
    };

    const addQuestion = () => {
        const newId = `node-q${Date.now()}`;
        const lastQuestion = [...questionNodes].pop();
        const beforeEndId = lastQuestion?.id ?? startNode?.id;

        if (!beforeEndId || !endNode) return;

        const newNode: QuestionScriptNode = {
            id: newId,
            type: 'question',
            position: { x: 250, y: (endNode.position.y ?? 0) },
            data: { message: 'Nova pergunta...' },
        };

        // Update end node position
        const updatedNodes = [
            ...data.nodes.map((n) =>
                n.id === endNode.id ? { ...n, position: { ...n.position, y: n.position.y + 150 } } : n,
            ),
            newNode,
        ];

        // Remove edge from beforeEnd -> end, add beforeEnd -> new -> end
        const updatedEdges = [
            ...data.edges.filter((e) => !(e.source === beforeEndId && e.target === endNode.id)),
            { id: `e-${beforeEndId}-${newId}`, source: beforeEndId, target: newId },
            { id: `e-${newId}-${endNode.id}`, source: newId, target: endNode.id },
        ];

        setData('nodes', updatedNodes);
        setData('edges', updatedEdges);
    };

    const removeQuestion = (nodeId: string) => {
        if (questionNodes.length <= 1) return;

        const inEdge = data.edges.find((e) => e.target === nodeId);
        const outEdge = data.edges.find((e) => e.source === nodeId);

        if (!inEdge || !outEdge) return;

        const updatedNodes = data.nodes.filter((n) => n.id !== nodeId);
        const updatedEdges = [
            ...data.edges.filter((e) => e.source !== nodeId && e.target !== nodeId),
            { id: `e-${inEdge.source}-${outEdge.target}`, source: inEdge.source, target: outEdge.target },
        ];

        setData('nodes', updatedNodes);
        setData('edges', updatedEdges);
    };

    const handleSave = () => {
        put(route('question-scripts.update', script.id), {
            onSuccess: () => setIsEditing(false),
        });
    };

    const handleCancel = () => {
        setData({
            name: script.name,
            description: script.description ?? '',
            nodes: script.nodes,
            edges: script.edges,
        });
        setIsEditing(false);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={script.name} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-3xl mx-auto w-full">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <Link href={route('question-scripts.index')}>
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            {isEditing ? (
                                <div className="flex flex-col gap-2">
                                    <div>
                                        <Label htmlFor="name">Nome do roteiro</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="mt-1"
                                        />
                                        {errors.name && <p className="text-sm text-destructive mt-1">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="description">Descrição</Label>
                                        <textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('description', e.target.value)}
                                            rows={2}
                                            className="mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <h1 className="text-3xl font-bold tracking-tight">{script.name}</h1>
                                    <div className="flex items-center gap-3 mt-2">
                                        <Badge variant={script.is_active ? 'default' : 'outline'}>
                                            {script.is_active ? 'Ativo' : 'Inativo'}
                                        </Badge>
                                        <span className="text-sm text-muted-foreground">
                                            {questionNodes.length} pergunta{questionNodes.length !== 1 ? 's' : ''}
                                        </span>
                                    </div>
                                    {script.description && (
                                        <p className="mt-2 text-sm text-muted-foreground">{script.description}</p>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {isEditing ? (
                            <>
                                <Button variant="outline" onClick={handleCancel} disabled={processing}>
                                    <X className="h-4 w-4 mr-2" />
                                    Cancelar
                                </Button>
                                <Button onClick={handleSave} disabled={processing}>
                                    <Save className="h-4 w-4 mr-2" />
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </Button>
                            </>
                        ) : (
                            <Button variant="outline" onClick={() => setIsEditing(true)}>
                                <Pencil className="h-4 w-4 mr-2" />
                                Editar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Script Flow Visualization */}
                <div className="flex flex-col items-center gap-2">
                    {/* Start Node */}
                    {startNode && (
                        <>
                            <Card className="w-full border-blue-500/50 bg-blue-50/50 dark:bg-blue-950/20">
                                <CardContent className="flex items-start gap-3 py-4">
                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400">
                                        <Play className="h-4 w-4" />
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase tracking-wider mb-1">
                                            Mensagem Inicial
                                        </p>
                                        {isEditing ? (
                                            <textarea
                                                value={startNode.data.message}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => updateNodeMessage(startNode.id, e.target.value)}
                                                rows={2}
                                                className="text-sm flex w-full rounded-md border border-input bg-transparent px-3 py-2 shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
                                            />
                                        ) : (
                                            <p className="text-sm">{startNode.data.message}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                            <ArrowDown className="h-5 w-5 text-muted-foreground" />
                        </>
                    )}

                    {/* Question Nodes */}
                    {questionNodes.map((node, index) => (
                        <div key={node.id} className="w-full flex flex-col items-center gap-2">
                            <Card className="w-full">
                                <CardContent className="flex items-start gap-3 py-4">
                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-sm">
                                        {index + 1}
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                            Pergunta {index + 1}
                                        </p>
                                        {isEditing ? (
                                            <textarea
                                                value={node.data.message}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => updateNodeMessage(node.id, e.target.value)}
                                                rows={2}
                                                className="text-sm flex w-full rounded-md border border-input bg-transparent px-3 py-2 shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
                                            />
                                        ) : (
                                            <p className="text-sm">{node.data.message}</p>
                                        )}
                                    </div>
                                    {isEditing && questionNodes.length > 1 && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-destructive hover:text-destructive"
                                            onClick={() => removeQuestion(node.id)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                            <ArrowDown className="h-5 w-5 text-muted-foreground" />
                        </div>
                    ))}

                    {/* Add Question Button (edit mode) */}
                    {isEditing && (
                        <>
                            <Button variant="outline" className="w-full border-dashed" onClick={addQuestion}>
                                <Plus className="h-4 w-4 mr-2" />
                                Adicionar Pergunta
                            </Button>
                            <ArrowDown className="h-5 w-5 text-muted-foreground" />
                        </>
                    )}

                    {/* End Node */}
                    {endNode && (
                        <Card className="w-full border-green-500/50 bg-green-50/50 dark:bg-green-950/20">
                            <CardContent className="flex items-start gap-3 py-4">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400">
                                    <CheckCircle className="h-4 w-4" />
                                </div>
                                <div className="flex-1">
                                    <p className="text-xs font-semibold text-green-700 dark:text-green-400 uppercase tracking-wider mb-1">
                                        Mensagem Final
                                    </p>
                                    {isEditing ? (
                                        <textarea
                                            value={endNode.data.message}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => updateNodeMessage(endNode.id, e.target.value)}
                                            rows={2}
                                            className="text-sm flex w-full rounded-md border border-input bg-transparent px-3 py-2 shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none"
                                        />
                                    ) : (
                                        <p className="text-sm">{endNode.data.message}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Preview */}
                {!isEditing && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <MessageCircle className="h-4 w-4" />
                                Pré-visualização do Chat
                            </CardTitle>
                            <CardDescription>
                                Assim é como o aluno verá as perguntas no diário reflexivo
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3 py-2">
                                {orderedNodes
                                    .filter((n) => n.type !== 'end')
                                    .map((node, index) => (
                                        <div key={node.id} className="flex gap-2.5">
                                            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                <MessageCircle className="h-3.5 w-3.5" />
                                            </div>
                                            <div className="max-w-[75%] rounded-2xl rounded-tl-sm bg-muted px-3.5 py-2.5">
                                                <p className="text-sm whitespace-pre-wrap leading-relaxed">
                                                    {node.data.message}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
