import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Props {
    responseId: number;
    analysisId: number;
}

export function AnalysisReviewForm({ responseId, analysisId }: Props) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleReview = (action: 'approved' | 'rejected') => {
        setProcessing(true);
        router.post(
            route('diaries.review', {
                response: responseId,
                analysis: analysisId,
            }),
            { action, notes: notes || null },
            {
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-3">
            <textarea
                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                placeholder="Observações do professor (opcional)..."
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
            />
            <div className="flex items-center gap-2">
                <Button
                    variant="default"
                    size="sm"
                    disabled={processing}
                    onClick={() => handleReview('approved')}
                >
                    <CheckCircle className="h-4 w-4 mr-1.5" />
                    Aprovar
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    disabled={processing}
                    onClick={() => handleReview('rejected')}
                >
                    <XCircle className="h-4 w-4 mr-1.5" />
                    Rejeitar
                </Button>
            </div>
        </div>
    );
}
