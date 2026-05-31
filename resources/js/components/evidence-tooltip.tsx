import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Quote } from 'lucide-react';

/**
 * Mostra, sob demanda, o trecho literal do diário que sustenta um alerta.
 * O texto é cópia exata da resposta do aluno (verificada no servidor).
 */
export function EvidenceTooltip({ evidence }: { evidence: string | null }) {
    if (!evidence) {
        return null;
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="sm" className="h-auto gap-1 px-2 py-1 text-xs text-muted-foreground">
                    <Quote className="h-3 w-3" />
                    Evidência
                </Button>
            </PopoverTrigger>
            <PopoverContent className="max-w-sm text-sm">
                <p className="italic">“{evidence}”</p>
            </PopoverContent>
        </Popover>
    );
}
