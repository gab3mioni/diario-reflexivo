import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { Plus } from 'lucide-react';
import { EdgeEditor } from './edge-editor';
import { NodeEditor } from './node-editor';

interface ScriptMeta {
    name: string;
    description: string;
}

interface Props {
    selectedNode: QuestionScriptNode | null;
    selectedEdge: QuestionScriptEdge | null;
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
    meta: ScriptMeta;
    onMetaChange: (meta: ScriptMeta) => void;
    onUpdateNode: (id: string, patch: Partial<QuestionScriptNode>) => void;
    onUpdateNodeData: (id: string, dataPatch: Partial<QuestionScriptNode['data']>) => void;
    onUpdateEdge: (id: string, patch: Partial<QuestionScriptEdge>) => void;
    onDeleteNode: (id: string) => void;
    onDeleteEdge: (id: string) => void;
    onClearSelection: () => void;
    onAddNode: (type: 'question' | 'free_talk' | 'end') => void;
    onAddEdge: (sourceId: string, targetId: string) => void;
}

export function nodeLabel(n: QuestionScriptNode): string {
    const type = {
        start: 'Início',
        question: 'Pergunta',
        free_talk: 'Conversa livre',
        end: 'Final',
    }[n.type];
    const snippet = (n.data.message ?? '').slice(0, 30);
    return `${type}: ${snippet || n.id}`;
}

export function SidebarEditor({
    selectedNode,
    selectedEdge,
    nodes,
    edges,
    meta,
    onMetaChange,
    onUpdateNode,
    onUpdateNodeData,
    onUpdateEdge,
    onDeleteNode,
    onDeleteEdge,
    onClearSelection,
    onAddNode,
    onAddEdge,
}: Props) {
    if (selectedNode) {
        return (
            <NodeEditor
                node={selectedNode}
                onUpdateNode={onUpdateNode}
                onUpdateNodeData={onUpdateNodeData}
                onDelete={() => onDeleteNode(selectedNode.id)}
                onClose={onClearSelection}
            />
        );
    }

    if (selectedEdge) {
        return (
            <EdgeEditor
                edge={selectedEdge}
                nodes={nodes}
                onUpdate={onUpdateEdge}
                onDelete={() => onDeleteEdge(selectedEdge.id)}
                onClose={onClearSelection}
            />
        );
    }

    return (
        <DefaultPanel
            meta={meta}
            nodes={nodes}
            edges={edges}
            onMetaChange={onMetaChange}
            onAddNode={onAddNode}
            onAddEdge={onAddEdge}
        />
    );
}

function DefaultPanel({
    meta,
    nodes,
    edges,
    onMetaChange,
    onAddNode,
    onAddEdge,
}: {
    meta: ScriptMeta;
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
    onMetaChange: (meta: ScriptMeta) => void;
    onAddNode: (type: 'question' | 'free_talk' | 'end') => void;
    onAddEdge: (sourceId: string, targetId: string) => void;
}) {
    const labelTextarea =
        'mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

    return (
        <div className="flex flex-col gap-5">
            <div>
                <h3 className="text-sm font-semibold">Metadados</h3>
                <div className="mt-3 flex flex-col gap-3">
                    <div>
                        <Label htmlFor="script-name">Nome</Label>
                        <Input
                            id="script-name"
                            value={meta.name}
                            onChange={(e) => onMetaChange({ ...meta, name: e.target.value })}
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <Label htmlFor="script-desc">Descrição</Label>
                        <textarea
                            id="script-desc"
                            value={meta.description}
                            onChange={(e) => onMetaChange({ ...meta, description: e.target.value })}
                            rows={3}
                            className={labelTextarea}
                        />
                    </div>
                </div>
            </div>

            <div>
                <h3 className="text-sm font-semibold">Adicionar nó</h3>
                <div className="mt-2 flex flex-col gap-2">
                    <Button variant="outline" size="sm" onClick={() => onAddNode('question')}>
                        <Plus className="size-4" /> Pergunta
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => onAddNode('free_talk')}>
                        <Plus className="size-4" /> Conversa livre
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => onAddNode('end')}>
                        <Plus className="size-4" /> Final
                    </Button>
                </div>
            </div>

            <AddEdgeForm nodes={nodes} edges={edges} onAddEdge={onAddEdge} />

            <p className="text-xs text-muted-foreground">
                Clique em qualquer nó ou conexão no canvas para editá-lo.
            </p>
        </div>
    );
}

function AddEdgeForm({
    nodes,
    edges,
    onAddEdge,
}: {
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
    onAddEdge: (s: string, t: string) => void;
}) {
    const sources = nodes.filter((n) => n.type !== 'end');
    const targets = nodes.filter((n) => n.type !== 'start');
    return (
        <div>
            <h3 className="text-sm font-semibold">Adicionar conexão</h3>
            <p className="mt-1 text-[11px] text-muted-foreground">
                Use para criar ramificações (presença, dúvidas, etc.).
            </p>
            <form
                className="mt-2 flex flex-col gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.currentTarget);
                    const source = String(fd.get('source') ?? '');
                    const target = String(fd.get('target') ?? '');
                    if (source && target && source !== target) {
                        onAddEdge(source, target);
                        (e.currentTarget as HTMLFormElement).reset();
                    }
                }}
            >
                <select
                    name="source"
                    defaultValue=""
                    className="rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                    required
                >
                    <option value="" disabled>De…</option>
                    {sources.map((n) => (
                        <option key={n.id} value={n.id}>
                            {nodeLabel(n)}
                        </option>
                    ))}
                </select>
                <select
                    name="target"
                    defaultValue=""
                    className="rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                    required
                >
                    <option value="" disabled>Para…</option>
                    {targets.map((n) => (
                        <option key={n.id} value={n.id}>
                            {nodeLabel(n)}
                        </option>
                    ))}
                </select>
                <Button type="submit" size="sm" variant="outline">
                    <Plus className="size-4" /> Conectar
                </Button>
            </form>
            <p className="mt-2 text-[11px] text-muted-foreground tabular-nums">
                {edges.length} conexões existentes
            </p>
        </div>
    );
}
