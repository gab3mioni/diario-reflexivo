import { TriangleAlert } from 'lucide-react';
import type { ResponseAlert } from '@/types/models';

interface Props {
    severity: 'low' | 'medium' | 'high' | null | undefined;
    count: number;
    types?: ResponseAlert['type'][];
}

const SEVERITY_LABEL: Record<'low' | 'medium' | 'high', string> = {
    low: 'Atenção',
    medium: 'Atenção média',
    high: 'Atenção alta',
};

const TYPE_LABEL: Record<ResponseAlert['type'], string> = {
    absence: 'falta',
    turn_cap_reached: 'limite de turnos',
    classifier_failure: 'falha de classificação',
    risk_signal: 'sinal de risco',
};

const TONE: Record<'low' | 'medium' | 'high', string> = {
    low: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200',
    medium: 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-700/60 dark:bg-orange-950/40 dark:text-orange-200',
    high: 'border-red-300 bg-red-50 text-red-800 dark:border-red-700/60 dark:bg-red-950/40 dark:text-red-200',
};

export function AttentionBadge({ severity, count, types }: Props) {
    if (!severity || count <= 0) return null;

    const tooltip = (types ?? []).map((t) => TYPE_LABEL[t]).join(', ');

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium ${TONE[severity]}`}
            title={tooltip || SEVERITY_LABEL[severity]}
        >
            <TriangleAlert className="size-3" aria-hidden="true" />
            {SEVERITY_LABEL[severity]}
            {count > 1 && <span className="tabular-nums opacity-80">·{count}</span>}
        </span>
    );
}
