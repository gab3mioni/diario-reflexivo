import { Bot, CheckCircle2, Loader2, ThumbsDown, TriangleAlert } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';

type AnalysisStatus = 'pending' | 'completed' | 'approved' | 'rejected' | 'failed' | string;

interface Props {
    status?: AnalysisStatus | null;
    className?: string;
}

export function AnalysisStatusBadge({ status, className }: Props) {
    if (!status) return null;

    switch (status) {
        case 'approved':
            return (
                <StatusBadge tone="done" icon={<CheckCircle2 aria-hidden="true" />} className={className}>
                    Aprovada
                </StatusBadge>
            );
        case 'rejected':
            return (
                <StatusBadge tone="destructive" icon={<ThumbsDown aria-hidden="true" />} className={className}>
                    Rejeitada
                </StatusBadge>
            );
        case 'completed':
            return (
                <StatusBadge tone="info" icon={<Bot aria-hidden="true" />} className={className}>
                    Revisão pendente
                </StatusBadge>
            );
        case 'pending':
            return (
                <StatusBadge tone="progress" icon={<Loader2 className="animate-spin" aria-hidden="true" />} className={className}>
                    Processando
                </StatusBadge>
            );
        case 'failed':
            return (
                <StatusBadge tone="destructive" icon={<TriangleAlert aria-hidden="true" />} className={className}>
                    Falhou
                </StatusBadge>
            );
        default:
            return null;
    }
}
