import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { QuestionScriptNode } from '@/types/models';
import { Plus, Trash2, X } from 'lucide-react';

const labelTextarea =
    'mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

interface NodeEditorProps {
    node: QuestionScriptNode;
    onUpdateNode: (id: string, patch: Partial<QuestionScriptNode>) => void;
    onUpdateNodeData: (id: string, dataPatch: Partial<QuestionScriptNode['data']>) => void;
    onDelete: () => void;
    onClose: () => void;
}

export function NodeEditor({ node, onUpdateNode, onUpdateNodeData, onDelete, onClose }: NodeEditorProps) {
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
