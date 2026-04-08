import * as React from 'react'
import { AlertTriangleIcon } from 'lucide-react'
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { cn } from '@/lib/utils'

type Tone = 'default' | 'destructive'

interface ConfirmDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    title: React.ReactNode
    description?: React.ReactNode
    confirmLabel?: string
    cancelLabel?: string
    tone?: Tone
    loading?: boolean
    onConfirm: () => void | Promise<void>
}

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    tone = 'default',
    loading = false,
    onConfirm,
}: ConfirmDialogProps) {
    const destructive = tone === 'destructive'

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-start gap-4">
                        {destructive && (
                            <div
                                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                                aria-hidden="true"
                            >
                                <AlertTriangleIcon className="size-5" />
                            </div>
                        )}
                        <div className="flex-1 space-y-1.5">
                            <DialogTitle className={cn(destructive && 'text-left')}>
                                {title}
                            </DialogTitle>
                            {description && (
                                <DialogDescription className={cn(destructive && 'text-left')}>
                                    {description}
                                </DialogDescription>
                            )}
                        </div>
                    </div>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={loading}>
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={loading}
                        onClick={() => void onConfirm()}
                    >
                        {loading && <Spinner className="size-4" />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
