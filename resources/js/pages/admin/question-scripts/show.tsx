import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FlowCanvas } from '@/components/admin/question-script/flow-canvas';
import { SidebarEditor } from '@/components/admin/question-script/sidebar-editor';
import AppLayout from '@/layouts/app-layout';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ScriptDetail {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
}

interface Props {
    script: ScriptDetail;
}

export default function AdminQuestionScriptShow({ script }: Props) {
    const breadcrumbs = [
        { title: 'Roteiros de Perguntas', href: '/question-scripts' },
        { title: script.name, href: `/question-scripts/${script.id}` },
    ];

    const [name, setName] = useState(script.name);
    const [description, setDescription] = useState(script.description ?? '');
    const [nodes, setNodes] = useState<QuestionScriptNode[]>(script.nodes);
    const [edges, setEdges] = useState<QuestionScriptEdge[]>(script.edges);
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);

    const selectedNode = useMemo(
        () => nodes.find((n) => n.id === selectedNodeId) ?? null,
        [nodes, selectedNodeId],
    );
    const selectedEdge = useMemo(
        () => edges.find((e) => e.id === selectedEdgeId) ?? null,
        [edges, selectedEdgeId],
    );

    const questionsCount = nodes.filter((n) => n.type === 'question').length;
    const freeTalksCount = nodes.filter((n) => n.type === 'free_talk').length;

    const updateNode = (id: string, patch: Partial<QuestionScriptNode>) => {
        setNodes((prev) => prev.map((n) => (n.id === id ? { ...n, ...patch } : n)));
    };

    const updateNodeData = (id: string, dataPatch: Partial<QuestionScriptNode['data']>) => {
        setNodes((prev) =>
            prev.map((n) => (n.id === id ? { ...n, data: { ...n.data, ...dataPatch } } : n)),
        );
    };

    const updateEdge = (id: string, patch: Partial<QuestionScriptEdge>) => {
        setEdges((prev) => prev.map((e) => (e.id === id ? { ...e, ...patch } : e)));
    };

    const deleteNode = (id: string) => {
        if (nodes.find((n) => n.id === id)?.type === 'start') return;
        setNodes((prev) => prev.filter((n) => n.id !== id));
        setEdges((prev) => prev.filter((e) => e.source !== id && e.target !== id));
        setSelectedNodeId(null);
    };

    const deleteEdge = (id: string) => {
        setEdges((prev) => prev.filter((e) => e.id !== id));
        setSelectedEdgeId(null);
    };

    const addNode = (type: 'question' | 'free_talk' | 'end') => {
        const id = `node-${type}-${Date.now()}`;
        const baseY = nodes.reduce((max, n) => Math.max(max, n.position.y), 0);
        const data: QuestionScriptNode['data'] = (() => {
            if (type === 'question') {
                return {
                    message: 'Nova pergunta',
                    collection_type: 'free_text',
                };
            }
            if (type === 'free_talk') {
                return {
                    message: 'Quer me contar mais sobre isso?',
                    closing_message: 'Obrigado por compartilhar.',
                    max_turns: 3,
                };
            }
            return { message: 'Obrigado pela sua reflexão!' };
        })();
        const newNode: QuestionScriptNode = {
            id,
            type,
            position: { x: 320, y: baseY + 160 },
            data,
        };
        setNodes((prev) => [...prev, newNode]);
        setSelectedNodeId(id);
        setSelectedEdgeId(null);
    };

    const addEdge = (sourceId: string, targetId: string) => {
        const id = `e-${sourceId}-${targetId}-${Date.now()}`;
        const sourceHasEdges = edges.some((e) => e.source === sourceId);
        const newEdge: QuestionScriptEdge = {
            id,
            source: sourceId,
            target: targetId,
            is_default: !sourceHasEdges,
            condition: { description: '' },
        };
        setEdges((prev) => [...prev, newEdge]);
        setSelectedEdgeId(id);
        setSelectedNodeId(null);
    };

    const validate = (): string[] => {
        const errs: string[] = [];
        const starts = nodes.filter((n) => n.type === 'start');
        const ends = nodes.filter((n) => n.type === 'end');
        if (starts.length !== 1) errs.push('O roteiro precisa ter exatamente um nó inicial.');
        if (ends.length < 1) errs.push('O roteiro precisa ter pelo menos um nó final.');

        const outgoingBySource: Record<string, QuestionScriptEdge[]> = {};
        for (const e of edges) {
            outgoingBySource[e.source] = [...(outgoingBySource[e.source] ?? []), e];
        }

        for (const [sourceId, list] of Object.entries(outgoingBySource)) {
            if (list.length > 1) {
                const defaults = list.filter((e) => e.is_default);
                if (defaults.length !== 1) {
                    errs.push(`O nó "${sourceId}" precisa ter exatamente uma conexão padrão.`);
                }
            }
            const sourceNode = nodes.find((n) => n.id === sourceId);
            if (sourceNode?.type === 'question' && sourceNode.data.collection_type === 'option' && list.length > 1) {
                const optionLabels = (sourceNode.data.options ?? []).map((o) => o.label.trim().toLowerCase());
                for (const e of list) {
                    if (e.is_default) continue;
                    const label = (e.condition?.description ?? '').trim().toLowerCase();
                    if (!label || !optionLabels.includes(label)) {
                        errs.push(`A conexão "${e.id}" não bate com nenhuma opção do nó "${sourceId}".`);
                    }
                }
            }
        }

        const reachesEnd = (startId: string): boolean => {
            const stack = [startId];
            const seen = new Set<string>();
            while (stack.length) {
                const id = stack.pop()!;
                if (seen.has(id)) continue;
                seen.add(id);
                const n = nodes.find((x) => x.id === id);
                if (!n) continue;
                if (n.type === 'end') return true;
                for (const e of outgoingBySource[id] ?? []) stack.push(e.target);
            }
            return false;
        };

        for (const n of nodes) {
            if (n.type === 'end') continue;
            if (!reachesEnd(n.id)) {
                errs.push(`O nó "${n.id}" não alcança nenhum nó final.`);
            }
        }
        return errs;
    };

    const handleSave = () => {
        const errs = validate();
        setErrors(errs);
        if (errs.length > 0) return;

        setSaving(true);
        router.put(
            route('question-scripts.update', script.id),
            { name, description, nodes, edges },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onError: (serverErrors) => {
                    const backendErrors = Object.values(serverErrors).flat();
                    setErrors((prev) => [...prev, ...backendErrors]);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={script.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Link href={route('question-scripts.index')}>
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{name}</h1>
                            <div className="mt-1 flex items-center gap-3">
                                <Badge variant={script.is_active ? 'default' : 'outline'}>
                                    {script.is_active ? 'Ativo' : 'Inativo'}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {questionsCount} pergunta{questionsCount !== 1 ? 's' : ''} · {freeTalksCount} conversa{freeTalksCount !== 1 ? 's' : ''} livre{freeTalksCount !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>
                    </div>
                    <Button onClick={handleSave} disabled={saving}>
                        <Save className="h-4 w-4 mr-2" />
                        {saving ? 'Salvando...' : 'Salvar roteiro'}
                    </Button>
                </div>

                {errors.length > 0 && (
                    <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3">
                        <p className="text-sm font-medium text-destructive">Não foi possível salvar:</p>
                        <ul className="mt-1 list-disc pl-5 text-xs text-destructive">
                            {errors.map((err, i) => (
                                <li key={i}>{err}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
                    <FlowCanvas
                        nodes={nodes}
                        edges={edges}
                        selectedNodeId={selectedNodeId}
                        selectedEdgeId={selectedEdgeId}
                        onSelectNode={(id) => {
                            setSelectedNodeId(id);
                            setSelectedEdgeId(null);
                        }}
                        onSelectEdge={(id) => {
                            setSelectedEdgeId(id);
                            setSelectedNodeId(null);
                        }}
                        onClearSelection={() => {
                            setSelectedNodeId(null);
                            setSelectedEdgeId(null);
                        }}
                    />
                    <div className="rounded-lg border border-border/60 bg-card p-4">
                        <SidebarEditor
                            selectedNode={selectedNode}
                            selectedEdge={selectedEdge}
                            nodes={nodes}
                            edges={edges}
                            meta={{ name, description }}
                            onMetaChange={(m) => {
                                setName(m.name);
                                setDescription(m.description);
                            }}
                            onUpdateNode={updateNode}
                            onUpdateNodeData={updateNodeData}
                            onUpdateEdge={updateEdge}
                            onDeleteNode={deleteNode}
                            onDeleteEdge={deleteEdge}
                            onClearSelection={() => {
                                setSelectedNodeId(null);
                                setSelectedEdgeId(null);
                            }}
                            onAddNode={addNode}
                            onAddEdge={addEdge}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
