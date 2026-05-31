export type AlertSeverity = 'info' | 'warning' | 'critical';

export type AlertStatus = 'pending' | 'acknowledged' | 'dismissed';

export type AlertType =
    | 'desmotivacao'
    | 'sobrecarga'
    | 'dificuldade_conceitual'
    | 'ausencia_reflexao'
    | 'sinal_socioemocional'
    | 'inconsistencia'
    | 'autoria_suspeita';

export interface DiaryAnalysisAlert {
    id: number;
    type: AlertType;
    severity: AlertSeverity;
    title: string;
    detail: string | null;
    evidence: string | null;
    confidence: number | null;
    status: AlertStatus;
    teacher_note: string | null;
    reviewed_at: string | null;
}

/**
 * Blocos opcionais do resultado da análise (schema v2+). Chaveados por
 * indicador; ausentes quando o prompt não os produz.
 */
export interface AnalysisExtensions {
    evidencias?: Partial<Record<string, string>>;
    confianca?: Partial<Record<string, number>>;
}
