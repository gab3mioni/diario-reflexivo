import { Badge } from '@/components/ui/badge';

/**
 * Exibe a confiança (0–100) da IA num alerta ou indicador. Nada quando ausente.
 */
export function ConfidenceBadge({ value }: { value: number | null }) {
    if (value === null) {
        return null;
    }

    return (
        <Badge variant="outline" className="font-normal">
            {value}% confiança
        </Badge>
    );
}
