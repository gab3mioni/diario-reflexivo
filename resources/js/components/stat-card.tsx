import * as React from 'react'
import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

type Tone =
    | 'default'
    | 'success'
    | 'warning'
    | 'info'
    | 'pending'
    | 'progress'
    | 'done'

const toneStyles: Record<Tone, { bg: string; icon: string; ring: string }> = {
    default: {
        bg: 'bg-muted',
        icon: 'text-muted-foreground',
        ring: 'ring-border',
    },
    success: {
        bg: 'bg-success-muted',
        icon: 'text-success',
        ring: 'ring-success/20',
    },
    warning: {
        bg: 'bg-warning-muted',
        icon: 'text-warning',
        ring: 'ring-warning/20',
    },
    info: {
        bg: 'bg-info-muted',
        icon: 'text-info',
        ring: 'ring-info/20',
    },
    pending: {
        bg: 'bg-status-pending-muted',
        icon: 'text-status-pending',
        ring: 'ring-status-pending/20',
    },
    progress: {
        bg: 'bg-status-progress-muted',
        icon: 'text-status-progress',
        ring: 'ring-status-progress/20',
    },
    done: {
        bg: 'bg-status-done-muted',
        icon: 'text-status-done',
        ring: 'ring-status-done/20',
    },
}

interface StatCardProps extends React.HTMLAttributes<HTMLDivElement> {
    label: React.ReactNode
    value: React.ReactNode
    icon?: React.ReactNode
    tone?: Tone
    delta?: { value: number; label?: string }
    hint?: React.ReactNode
}

export function StatCard({
    label,
    value,
    icon,
    tone = 'default',
    delta,
    hint,
    className,
    ...props
}: StatCardProps) {
    const styles = toneStyles[tone]
    const positive = delta ? delta.value >= 0 : false

    return (
        <div
            className={cn(
                'group relative flex flex-col gap-3 rounded-xl border border-border/60 bg-card p-5 shadow-xs transition-shadow hover:shadow-sm',
                className,
            )}
            {...props}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-muted-foreground">
                    {label}
                </p>
                {icon && (
                    <div
                        className={cn(
                            'flex size-9 items-center justify-center rounded-lg ring-1 [&>svg]:size-4',
                            styles.bg,
                            styles.icon,
                            styles.ring,
                        )}
                        aria-hidden="true"
                    >
                        {icon}
                    </div>
                )}
            </div>
            <div className="flex items-baseline gap-2">
                <span className="text-3xl font-semibold tracking-tight tabular-nums text-foreground">
                    {value}
                </span>
                {delta && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 text-xs font-medium',
                            positive ? 'text-success' : 'text-destructive',
                        )}
                    >
                        {positive ? (
                            <ArrowUpIcon className="size-3" />
                        ) : (
                            <ArrowDownIcon className="size-3" />
                        )}
                        {Math.abs(delta.value)}
                        {delta.label && (
                            <span className="text-muted-foreground">
                                {' '}
                                {delta.label}
                            </span>
                        )}
                    </span>
                )}
            </div>
            {hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    )
}
