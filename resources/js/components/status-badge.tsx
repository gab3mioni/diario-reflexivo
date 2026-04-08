import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const statusBadgeVariants = cva(
    'inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium whitespace-nowrap [&>svg]:size-3 [&>svg]:shrink-0',
    {
        variants: {
            tone: {
                neutral:
                    'border-border bg-muted text-muted-foreground',
                pending:
                    'border-status-pending/20 bg-status-pending-muted text-status-pending',
                progress:
                    'border-status-progress/20 bg-status-progress-muted text-status-progress',
                done: 'border-status-done/20 bg-status-done-muted text-status-done',
                locked:
                    'border-status-locked/20 bg-status-locked-muted text-muted-foreground',
                success:
                    'border-success/20 bg-success-muted text-success',
                warning:
                    'border-warning/20 bg-warning-muted text-warning',
                info: 'border-info/20 bg-info-muted text-info',
                destructive:
                    'border-destructive/20 bg-destructive/10 text-destructive',
            },
            size: {
                sm: 'px-2 py-0.5 text-[11px]',
                md: 'px-2.5 py-1 text-xs',
            },
            dot: {
                true: '',
                false: '',
            },
        },
        defaultVariants: {
            tone: 'neutral',
            size: 'md',
            dot: false,
        },
    },
)

interface StatusBadgeProps
    extends React.HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof statusBadgeVariants> {
    icon?: React.ReactNode
    pulse?: boolean
}

export function StatusBadge({
    className,
    tone = 'neutral',
    size,
    dot,
    icon,
    pulse = false,
    children,
    ...props
}: StatusBadgeProps) {
    return (
        <span
            className={cn(statusBadgeVariants({ tone, size, dot }), className)}
            {...props}
        >
            {dot && (
                <span className="relative flex size-2">
                    {pulse && (
                        <span className="absolute inset-0 animate-ping rounded-full bg-current opacity-60" />
                    )}
                    <span className="relative size-2 rounded-full bg-current" />
                </span>
            )}
            {icon}
            {children}
        </span>
    )
}

export { statusBadgeVariants }
