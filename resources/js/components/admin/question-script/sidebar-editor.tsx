import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { Plus, Trash2, X } from 'lucide-react';

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

const labelTextarea =
    'mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

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

function nodeLabel(n: QuestionScriptNode): string {
    const type = {
        start: 'Início',
        question: 'Pergunta',
        free_talk: 'Conversa livre',
        end: 'Final',
    }[n.type];
    const snippet = (n.data.message ?? '').slice(0, 30);
    return `${type}: ${snippet || n.id}`;
}

function NodeEditor({
    node,
    onUpdateNode,
    onUpdateNodeData,
    onDelete,
    onClose,
}: {
    node: QuestionScriptNode;
    onUpdateNode: (id: string, patch: Partial<QuestionScriptNode>) => void;
    onUpdateNodeData: (id: string, dataPatch: Partial<QuestionScriptNode['data']>) => void;
    onDelete: () => void;
    onClose: () => void;
}) {
    const isQuestion = node.type === 'question';
    const isFreeTalk = node.type === 'free_talk';
    const collectionType = node.data.collection_type ?? 'free_text';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Editar nó</h3>
                <Button variant="ghost" size="icon" onClick={onClose}>
                    <X className="size-4" />
                </Button>
            </div>

            <div>
                <Label>Tipo</Label>
                <p className="mt-1 text-xs text-muted-foreground">
                    {{ start: 'Início', question: 'Pergunta', free_talk: 'Conversa livre', end: 'Final' }[node.type]}
                </p>
            </div>

            <div>
                <Label htmlFor="msg">Mensagem</Label>
                <textarea
                    id="msg"
                    value={node.data.message ?? ''}
                    onChange={(e) => onUpdateNodeData(node.id, { message: e.target.value })}
                    rows={4}
                    className={labelTextarea}
                />
            </div>

            {isQuestion && (
                <div>
                    <Label>Tipo de coleta</Label>
                    <div className="mt-1 flex gap-2">
                        <Button
                            type="button"
                            variant={collectionType === 'free_text' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => onUpdateNodeData(node.id, { collection_type: 'free_text', options: undefined })}
                        >
                            Texto livre
                        </Button>
                        <Button
                            type="button"
                            variant={collectionType === 'option' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() =>
                                onUpdateNodeData(node.id, {
                                    collection_type: 'option',
                                    options: node.data.options ?? [{ label: 'Sim' }, { label: 'Não' }],
                                })
                            }
                        >
                            Opções
                        </Button>
                    </div>
                </div>
            )}

            {isQuestion && collectionType === 'option' && (
                <OptionsEditor
                    options={node.data.options ?? []}
                    onChange={(options) => onUpdateNodeData(node.id, { options })}
                />
            )}

            {isFreeTalk && (
                <>
                    <div>
                        <Label htmlFor="closing">Mensagem de encerramento</Label>
                        <textarea
                            id="closing"
                            value={node.data.closing_message ?? ''}
                            onChange={(e) => onUpdateNodeData(node.id, { closing_message: e.target.value })}
                            rows={2}
                            className={labelTextarea}
                        />
                    </div>
                    <div>
                        <Label htmlFor="max-turns">Máx. de turnos</Label>
                        <Input
                            id="max-turns"
                            type="number"
                            min={1}
                            max={6}
                            value={node.data.max_turns ?? 3}
                            onChange={(e) => onUpdateNodeData(node.id, { max_turns: Math.max(1, Math.min(6, Number(e.target.value) || 3)) })}
                            className="mt-1"
                        />
                    </div>
                </>
            )}

            {(isQuestion || isFreeTalk) && (
                <AlertEditor
                    alert={node.data.alert}
                    onChange={(alert) => onUpdateNodeData(node.id, { alert })}
                />
            )}

            {node.type !== 'start' && (
                <Button variant="outline" size="sm" onClick={onDelete} className="text-destructive hover:text-destructive">
                    <Trash2 className="size-4" /> Excluir nó
                </Button>
            )}
        </div>
    );
}

function OptionsEditor({
    options,
    onChange,
}: {
    options: { label: string }[];
    onChange: (opts: { label: string }[]) => void;
}) {
    return (
        <div>
            <Label>Opções</Label>
            <p className="text-[11px] text-muted-foreground">
                Cada opção precisa ter uma conexão com o mesmo rótulo no campo "condição".
            </p>
            <div className="mt-2 flex flex-col gap-2">
                {options.map((opt, i) => (
                    <div key={i} className="flex gap-2">
                        <Input
                            value={opt.label}
                            onChange={(e) => {
                                const next = [...options];
                                next[i] = { label: e.target.value };
                                onChange(next);
                            }}
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange(options.filter((_, j) => j !== i))}
                            disabled={options.length <= 2}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
                <Button variant="outline" size="sm" onClick={() => onChange([...options, { label: 'Nova opção' }])}>
                    <Plus className="size-4" /> Adicionar opção
                </Button>
            </div>
        </div>
    );
}

function AlertEditor({
    alert,
    onChange,
}: {
    alert: QuestionScriptNode['data']['alert'];
    onChange: (alert: QuestionScriptNode['data']['alert']) => void;
}) {
    const enabled = !!alert;
    return (
        <div className="rounded-md border border-dashed border-border/70 p-3">
            <div className="flex items-center justify-between">
                <Label>Disparar alerta ao entrar</Label>
                <Button
                    type="button"
                    size="sm"
                    variant={enabled ? 'default' : 'outline'}
                    onClick={() =>
                        onChange(enabled ? undefined : { type: 'absence', severity: 'medium' })
                    }
                >
                    {enabled ? 'Ativo' : 'Desativado'}
                </Button>
            </div>
            {enabled && alert && (
                <div className="mt-3 flex flex-col gap-2">
                    <div>
                        <Label>Tipo</Label>
                        <select
                            value={alert.type}
                            onChange={(e) => onChange({ ...alert, type: e.target.value as 'absence' | 'risk_signal' })}
                            className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                        >
                            <option value="absence">Falta</option>
                            <option value="risk_signal">Sinal de risco</option>
                        </select>
                    </div>
                    <div>
                        <Label>Severidade</Label>
                        <select
                            value={alert.severity}
                            onChange={(e) => onChange({ ...alert, severity: e.target.value as 'low' | 'medium' | 'high' })}
                            className="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                        >
                            <option value="low">Baixa</option>
                            <option value="medium">Média</option>
                            <option value="high">Alta</option>
                        </select>
                    </div>
                    <div>
                        <Label>Motivo (opcional)</Label>
                        <Input
                            value={alert.reason ?? ''}
                            onChange={(e) => onChange({ ...alert, reason: e.target.value || null })}
                            className="mt-1"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

function EdgeEditor({
    edge,
    nodes,
    onUpdate,
    onDelete,
    onClose,
}: {
    edge: QuestionScriptEdge;
    nodes: QuestionScriptNode[];
    onUpdate: (id: string, patch: Partial<QuestionScriptEdge>) => void;
    onDelete: () => void;
    onClose: () => void;
}) {
    const sourceNode = nodes.find((n) => n.id === edge.source);
    const targetNode = nodes.find((n) => n.id === edge.target);
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Editar conexão</h3>
                <Button variant="ghost" size="icon" onClick={onClose}>
                    <X className="size-4" />
                </Button>
            </div>
            <div className="text-xs text-muted-foreground">
                <span className="font-medium text-foreground">{sourceNode ? nodeLabel(sourceNode) : edge.source}</span>
                <span className="mx-1">→</span>
                <span className="font-medium text-foreground">{targetNode ? nodeLabel(targetNode) : edge.target}</span>
            </div>
            <div>
                <Label htmlFor="cond">Condição / Rótulo</Label>
                <p className="text-[11px] text-muted-foreground">
                    Para nós de opção: o rótulo exato. Para texto livre: descreva o caso que ativa esta ramificação (a IA usa pra decidir).
                </p>
                <textarea
                    id="cond"
                    value={edge.condition?.description ?? ''}
                    onChange={(e) =>
                        onUpdate(edge.id, {
                            condition: { description: e.target.value },
                        })
                    }
                    rows={3}
                    className={labelTextarea}
                />
            </div>
            <div className="flex items-center justify-between">
                <Label htmlFor="default">Conexão padrão</Label>
                <input
                    id="default"
                    type="checkbox"
                    checked={!!edge.is_default}
                    onChange={(e) => onUpdate(edge.id, { is_default: e.target.checked })}
                />
            </div>
            <p className="text-[11px] text-muted-foreground">
                A conexão padrão é o caminho seguido quando nenhuma condição bate (fallback do classificador).
            </p>
            <Button variant="outline" size="sm" onClick={onDelete} className="text-destructive hover:text-destructive">
                <Trash2 className="size-4" /> Excluir conexão
            </Button>
        </div>
    );
}
