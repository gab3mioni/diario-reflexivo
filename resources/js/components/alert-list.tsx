import { update } from '@/actions/App/Http/Controllers/DiaryAnalysisAlertController';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { EvidenceTooltip } from '@/components/evidence-tooltip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    ALERT_SEVERITY_LABELS,
    ALERT_STATUS_LABELS,
    ALERT_TYPE_LABELS,
    requiresTeacherNote,
    severityBadgeVariant,
} from '@/lib/alerts';
import type { AlertStatus, DiaryAnalysisAlert } from '@/types/analysis';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface AlertListProps {
    responseId: number;
    alerts: DiaryAnalysisAlert[];
}

/**
 * Lista os alertas da análise e permite a triagem do professor. Alertas são
 * apenas sinais human-gated: reconhecer/descartar/reabrir é decisão humana.
 */
export function AlertList({ responseId, alerts }: AlertListProps) {
    if (alerts.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 text-amber-500" />
                    Alertas ({alerts.length})
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {alerts.map((alert) => (
                    <AlertRow
                        key={alert.id}
                        responseId={responseId}
                        alert={alert}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function AlertRow({
    responseId,
    alert,
}: {
    responseId: number;
    alert: DiaryAnalysisAlert;
}) {
    const form = useForm({ teacher_note: alert.teacher_note ?? '' });

    const triage = (status: AlertStatus) => {
        form.transform((data) => ({ ...data, status }));
        form.patch(update.url({ response: responseId, alert: alert.id }), {
            preserveScroll: true,
        });
    };

    const dismissNoteRequired = requiresTeacherNote(alert.type, 'dismissed');

    return (
        <div className="rounded-md border p-3">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={severityBadgeVariant(alert.severity)}>
                    {ALERT_SEVERITY_LABELS[alert.severity]}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    {ALERT_TYPE_LABELS[alert.type]}
                </span>
                <Badge variant="outline" className="ml-auto font-normal">
                    {ALERT_STATUS_LABELS[alert.status]}
                </Badge>
            </div>

            <p className="mt-2 text-sm font-medium">{alert.title}</p>
            {alert.detail && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {alert.detail}
                </p>
            )}

            <div className="mt-2 flex flex-wrap items-center gap-2">
                <EvidenceTooltip evidence={alert.evidence} />
                <ConfidenceBadge value={alert.confidence} />
            </div>

            <div className="mt-3 space-y-1">
                <Label htmlFor={`note-${alert.id}`} className="text-xs">
                    {dismissNoteRequired
                        ? 'Nota (obrigatória para descartar)'
                        : 'Nota (opcional)'}
                </Label>
                <textarea
                    id={`note-${alert.id}`}
                    className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    value={form.data.teacher_note}
                    onChange={(e) =>
                        form.setData('teacher_note', e.target.value)
                    }
                    rows={2}
                />
                {form.errors.teacher_note && (
                    <p className="text-sm text-destructive">
                        {form.errors.teacher_note}
                    </p>
                )}
            </div>

            <div className="mt-3 flex flex-wrap gap-2">
                {alert.status !== 'acknowledged' && (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => triage('acknowledged')}
                    >
                        Reconhecer
                    </Button>
                )}
                {alert.status !== 'dismissed' && (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => triage('dismissed')}
                    >
                        Descartar
                    </Button>
                )}
                {alert.status !== 'pending' && (
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={() => triage('pending')}
                    >
                        Reabrir
                    </Button>
                )}
            </div>
        </div>
    );
}
