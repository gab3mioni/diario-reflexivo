import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AnalysisIndicators } from '@/components/analysis-indicators';
import { AnalysisReviewForm } from '@/components/analysis-review-form';
import type { DiaryAnalysis } from '@/types/models';
import { AlertTriangle, CheckCircle, Clock, Loader2, Star, Target, XCircle } from 'lucide-react';

const statusConfig: Record<DiaryAnalysis['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    pending: { label: 'Pendente', variant: 'secondary' },
    completed: { label: 'Concluída', variant: 'outline' },
    failed: { label: 'Falhou', variant: 'destructive' },
    approved: { label: 'Aprovada', variant: 'default' },
    rejected: { label: 'Rejeitada', variant: 'destructive' },
};

interface Props {
    analysis: DiaryAnalysis;
    responseId: number;
    isLatest?: boolean;
}

export function AnalysisCard({ analysis, responseId, isLatest = false }: Props) {
    const status = statusConfig[analysis.status];

    return (
        <Card className={isLatest ? 'border-primary/30' : ''}>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <CardTitle className="text-base">
                            Análise #{analysis.id}
                        </CardTitle>
                        {isLatest && <Badge variant="secondary">Mais recente</Badge>}
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={status.variant}>{status.label}</Badge>
                        <span className="text-xs text-muted-foreground">
                            {new Date(analysis.created_at).toLocaleDateString('pt-BR', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                    </div>
                </div>
                <p className="text-xs text-muted-foreground">
                    {analysis.provider_name} / {analysis.model_name} - Prompt v{analysis.prompt_version}
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                {analysis.status === 'pending' && (
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span className="text-sm">Análise em processamento...</span>
                    </div>
                )}

                {analysis.status === 'failed' && (
                    <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                        <p className="font-medium">Erro na análise:</p>
                        <p>{analysis.error_message}</p>
                    </div>
                )}

                {analysis.result && (
                    <>
                        {/* Summary */}
                        <div>
                            <h4 className="text-sm font-medium mb-1">Resumo</h4>
                            <p className="text-sm text-muted-foreground">{analysis.result.resumo}</p>
                        </div>

                        {/* Indicators */}
                        <div>
                            <h4 className="text-sm font-medium mb-2">Indicadores</h4>
                            <AnalysisIndicators indicadores={analysis.result.indicadores} />
                        </div>

                        {/* Strengths */}
                        {analysis.result.pontos_fortes.length > 0 && (
                            <div>
                                <h4 className="text-sm font-medium mb-1 flex items-center gap-1.5">
                                    <Star className="h-3.5 w-3.5 text-green-500" />
                                    Pontos Fortes
                                </h4>
                                <ul className="text-sm text-muted-foreground space-y-1">
                                    {analysis.result.pontos_fortes.map((p, i) => (
                                        <li key={i} className="flex items-start gap-2">
                                            <CheckCircle className="h-3.5 w-3.5 text-green-500 mt-0.5 shrink-0" />
                                            {p}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Concerns */}
                        {analysis.result.pontos_atencao.length > 0 && (
                            <div>
                                <h4 className="text-sm font-medium mb-1 flex items-center gap-1.5">
                                    <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />
                                    Pontos de Atenção
                                </h4>
                                <ul className="text-sm text-muted-foreground space-y-1">
                                    {analysis.result.pontos_atencao.map((p, i) => (
                                        <li key={i} className="flex items-start gap-2">
                                            <AlertTriangle className="h-3.5 w-3.5 text-amber-500 mt-0.5 shrink-0" />
                                            {p}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Suggested Actions */}
                        {analysis.result.sugestoes_acao.length > 0 && (
                            <div>
                                <h4 className="text-sm font-medium mb-1 flex items-center gap-1.5">
                                    <Target className="h-3.5 w-3.5 text-blue-500" />
                                    Sugestões de Ação
                                </h4>
                                <ul className="text-sm text-muted-foreground space-y-1">
                                    {analysis.result.sugestoes_acao.map((s, i) => (
                                        <li key={i} className="flex items-start gap-2">
                                            <Target className="h-3.5 w-3.5 text-blue-500 mt-0.5 shrink-0" />
                                            {s}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Teacher review status */}
                        {analysis.teacher_notes && (
                            <div className="rounded-md bg-muted p-3">
                                <h4 className="text-sm font-medium mb-1">Observações do Professor</h4>
                                <p className="text-sm text-muted-foreground">{analysis.teacher_notes}</p>
                                {analysis.reviewed_at && (
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Revisado em{' '}
                                        {new Date(analysis.reviewed_at).toLocaleDateString('pt-BR', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Review form - only for completed (not yet reviewed) analyses */}
                        {analysis.status === 'completed' && (
                            <div className="border-t pt-4">
                                <h4 className="text-sm font-medium mb-2">Revisar Análise</h4>
                                <AnalysisReviewForm
                                    responseId={responseId}
                                    analysisId={analysis.id}
                                />
                            </div>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
