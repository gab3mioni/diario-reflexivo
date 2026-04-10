import { Handle, Position, type NodeProps } from '@xyflow/react';
import { CheckCircle, MessageCircle, MessagesSquare, Play, TriangleAlert } from 'lucide-react';
import type { QuestionScriptNode } from '@/types/models';

type NodeData = QuestionScriptNode['data'];

function NodeShell({
    tone,
    icon,
    title,
    children,
    selected,
    hasAlert,
}: {
    tone: 'blue' | 'primary' | 'amber' | 'green';
    icon: React.ReactNode;
    title: string;
    children: React.ReactNode;
    selected?: boolean;
    hasAlert?: boolean;
}) {
    const toneClasses = {
        blue: 'border-blue-500/60 bg-blue-50 dark:bg-blue-950/30',
        primary: 'border-primary/60 bg-card',
        amber: 'border-amber-500/60 bg-amber-50 dark:bg-amber-950/30',
        green: 'border-green-500/60 bg-green-50 dark:bg-green-950/30',
    }[tone];

    const iconClasses = {
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
        primary: 'bg-primary/10 text-primary',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
        green: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    }[tone];

    return (
        <div
            className={[
                'min-w-[220px] max-w-[260px] rounded-lg border-2 px-3 py-2 shadow-sm transition-all',
                toneClasses,
                selected ? 'ring-2 ring-ring ring-offset-2 ring-offset-background' : '',
            ].join(' ')}
        >
            <div className="flex items-center gap-2">
                <div className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${iconClasses}`}>
                    {icon}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {title}
                    </p>
                </div>
                {hasAlert && (
                    <TriangleAlert className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" aria-label="Com alerta" />
                )}
            </div>
            <div className="mt-1.5 text-xs text-foreground/90 line-clamp-3 whitespace-pre-wrap break-words">
                {children}
            </div>
        </div>
    );
}

export function StartNode({ data, selected }: NodeProps<{ data: NodeData }>) {
    const d = (data as unknown as NodeData);
    return (
        <>
            <NodeShell tone="blue" icon={<Play className="h-3.5 w-3.5" />} title="Início" selected={selected}>
                {d.message || 'Mensagem inicial'}
            </NodeShell>
            <Handle type="source" position={Position.Bottom} className="!bg-blue-500" />
        </>
    );
}

export function QuestionNode({ data, selected }: NodeProps<{ data: NodeData }>) {
    const d = (data as unknown as NodeData);
    const ct = d.collection_type ?? 'free_text';
    return (
        <>
            <Handle type="target" position={Position.Top} className="!bg-primary" />
            <NodeShell
                tone="primary"
                icon={<MessageCircle className="h-3.5 w-3.5" />}
                title={`Pergunta · ${ct === 'option' ? 'Opções' : 'Texto livre'}`}
                selected={selected}
                hasAlert={!!d.alert}
            >
                {d.message || 'Pergunta vazia'}
                {ct === 'option' && d.options && d.options.length > 0 && (
                    <div className="mt-1.5 flex flex-wrap gap-1">
                        {d.options.map((opt, i) => (
                            <span
                                key={i}
                                className="rounded-full border border-border/70 bg-background px-1.5 py-0.5 text-[10px] text-muted-foreground"
                            >
                                {opt.label}
                            </span>
                        ))}
                    </div>
                )}
            </NodeShell>
            <Handle type="source" position={Position.Bottom} className="!bg-primary" />
        </>
    );
}

export function FreeTalkNode({ data, selected }: NodeProps<{ data: NodeData }>) {
    const d = (data as unknown as NodeData);
    const turns = d.max_turns ?? 3;
    return (
        <>
            <Handle type="target" position={Position.Top} className="!bg-amber-500" />
            <NodeShell
                tone="amber"
                icon={<MessagesSquare className="h-3.5 w-3.5" />}
                title={`Conversa livre · até ${turns} turnos`}
                selected={selected}
                hasAlert={!!d.alert}
            >
                {d.message || 'Mensagem de abertura'}
            </NodeShell>
            <Handle type="source" position={Position.Bottom} className="!bg-amber-500" />
        </>
    );
}

export function EndNode({ data, selected }: NodeProps<{ data: NodeData }>) {
    const d = (data as unknown as NodeData);
    return (
        <>
            <Handle type="target" position={Position.Top} className="!bg-green-500" />
            <NodeShell tone="green" icon={<CheckCircle className="h-3.5 w-3.5" />} title="Final" selected={selected}>
                {d.message || 'Mensagem final'}
            </NodeShell>
        </>
    );
}

export const nodeTypes = {
    start: StartNode,
    question: QuestionNode,
    free_talk: FreeTalkNode,
    end: EndNode,
};
