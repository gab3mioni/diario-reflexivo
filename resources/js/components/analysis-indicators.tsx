import type { DiaryAnalysisResult } from '@/types/models';

const indicatorLabels: Record<string, string> = {
    compreensao: 'Compreensão',
    engajamento: 'Engajamento',
    pensamento_critico: 'Pensamento Crítico',
    clareza_expressao: 'Clareza de Expressão',
    reflexao_pessoal: 'Reflexão Pessoal',
};

const scoreColors: Record<number, string> = {
    1: 'bg-red-500',
    2: 'bg-orange-500',
    3: 'bg-yellow-500',
    4: 'bg-blue-500',
    5: 'bg-green-500',
};

interface Props {
    indicadores: DiaryAnalysisResult['indicadores'];
}

export function AnalysisIndicators({ indicadores }: Props) {
    return (
        <div className="space-y-3">
            {Object.entries(indicadores).map(([key, value]) => (
                <div key={key} className="space-y-1">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {indicatorLabels[key] || key}
                        </span>
                        <span className="font-medium">{value}/5</span>
                    </div>
                    <div className="h-2 w-full rounded-full bg-muted">
                        <div
                            className={`h-2 rounded-full transition-all ${scoreColors[value] || 'bg-gray-400'}`}
                            style={{ width: `${(value / 5) * 100}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}
