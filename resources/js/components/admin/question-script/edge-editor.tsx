import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { Trash2, X } from 'lucide-react';
import { nodeLabel } from './sidebar-editor';

const labelTextarea =
    'mt-1 flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

interface EdgeEditorProps {
    edge: QuestionScriptEdge;
    nodes: QuestionScriptNode[];
    onUpdate: (id: string, patch: Partial<QuestionScriptEdge>) => void;
    onDelete: () => void;
    onClose: () => void;
}

export function EdgeEditor({ edge, nodes, onUpdate, onDelete, onClose }: EdgeEditorProps) {
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
