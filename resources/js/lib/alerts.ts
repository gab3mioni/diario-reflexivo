import type { AlertSeverity, AlertStatus, AlertType } from '@/types/analysis';

export const ALERT_TYPE_LABELS: Record<AlertType, string> = {
    desmotivacao: 'Desmotivação',
    sobrecarga: 'Sobrecarga',
    dificuldade_conceitual: 'Dificuldade conceitual',
    ausencia_reflexao: 'Ausência de reflexão',
    sinal_socioemocional: 'Sinal socioemocional',
    inconsistencia: 'Inconsistência',
    autoria_suspeita: 'Autoria suspeita',
};

export const ALERT_SEVERITY_LABELS: Record<AlertSeverity, string> = {
    info: 'Informativo',
    warning: 'Atenção',
    critical: 'Crítico',
};

export const ALERT_STATUS_LABELS: Record<AlertStatus, string> = {
    pending: 'Pendente',
    acknowledged: 'Reconhecido',
    dismissed: 'Descartado',
};

/**
 * Alertas socioemocionais exigem uma nota do professor ao serem descartados.
 * Espelha a regra do servidor (UpdateAnalysisAlertRequest): um sinal sensível
 * não deve ser silenciado sem justificativa.
 */
export function requiresTeacherNote(type: AlertType, status: AlertStatus): boolean {
    return type === 'sinal_socioemocional' && status === 'dismissed';
}

export function severityBadgeVariant(severity: AlertSeverity): 'secondary' | 'default' | 'destructive' {
    switch (severity) {
        case 'critical':
            return 'destructive';
        case 'warning':
            return 'default';
        default:
            return 'secondary';
    }
}
