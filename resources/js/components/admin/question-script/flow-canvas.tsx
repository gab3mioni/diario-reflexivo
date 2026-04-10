import {
    Background,
    BackgroundVariant,
    Controls,
    MarkerType,
    ReactFlow,
    ReactFlowProvider,
    type Edge,
    type Node,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { useMemo } from 'react';
import type { QuestionScriptEdge, QuestionScriptNode } from '@/types/models';
import { nodeTypes } from './nodes';

interface Props {
    nodes: QuestionScriptNode[];
    edges: QuestionScriptEdge[];
    selectedNodeId: string | null;
    selectedEdgeId: string | null;
    onSelectNode: (id: string) => void;
    onSelectEdge: (id: string) => void;
    onClearSelection: () => void;
}

export function FlowCanvas({
    nodes,
    edges,
    selectedNodeId,
    selectedEdgeId,
    onSelectNode,
    onSelectEdge,
    onClearSelection,
}: Props) {
    const flowNodes = useMemo<Node[]>(
        () =>
            nodes.map((n) => ({
                id: n.id,
                type: n.type,
                position: n.position,
                data: n.data as unknown as Record<string, unknown>,
                selected: n.id === selectedNodeId,
                draggable: false,
                connectable: false,
            })),
        [nodes, selectedNodeId],
    );

    const flowEdges = useMemo<Edge[]>(
        () =>
            edges.map((e) => {
                const isDefault = !!e.is_default;
                const condition = e.condition?.description ?? '';
                return {
                    id: e.id,
                    source: e.source,
                    target: e.target,
                    label: condition || (isDefault ? 'padrão' : ''),
                    selected: e.id === selectedEdgeId,
                    animated: !isDefault,
                    style: {
                        stroke: isDefault ? 'var(--primary)' : 'var(--muted-foreground)',
                        strokeWidth: e.id === selectedEdgeId ? 2.5 : 1.5,
                        strokeDasharray: isDefault ? undefined : '4 2',
                    },
                    labelStyle: { fontSize: 11 },
                    labelBgStyle: { fill: 'var(--background)' },
                    markerEnd: { type: MarkerType.ArrowClosed },
                };
            }),
        [edges, selectedEdgeId],
    );

    return (
        <div className="h-[640px] w-full overflow-hidden rounded-lg border border-border/60 bg-background">
            <ReactFlowProvider>
                <ReactFlow
                    nodes={flowNodes}
                    edges={flowEdges}
                    nodeTypes={nodeTypes}
                    onNodeClick={(_, n) => onSelectNode(n.id)}
                    onEdgeClick={(_, e) => onSelectEdge(e.id)}
                    onPaneClick={onClearSelection}
                    nodesDraggable={false}
                    nodesConnectable={false}
                    elementsSelectable
                    fitView
                    proOptions={{ hideAttribution: true }}
                >
                    <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
                    <Controls showInteractive={false} />
                </ReactFlow>
            </ReactFlowProvider>
        </div>
    );
}
