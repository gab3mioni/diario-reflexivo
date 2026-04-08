import * as React from 'react'
import { cn } from '@/lib/utils'

interface EmptyStateProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'title'> {
    icon?: React.ReactNode
    title: React.ReactNode
    description?: React.ReactNode
    action?: React.ReactNode
    compact?: boolean
}

export function EmptyState({
    icon,
    title,
    description,
    action,
    compact = false,
    className,
    ...props
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center text-center',
                compact ? 'gap-2 py-8' : 'gap-3 py-12',
                className,
            )}
            {...props}
        >
            {icon && (
                <div
                    className={cn(
                        'mb-1 flex items-center justify-center rounded-full bg-muted text-muted-foreground',
                        compact ? 'size-10 [&>svg]:size-5' : 'size-14 [&>svg]:size-7',
                    )}
                    aria-hidden="true"
                >
                    {icon}
                </div>
            )}
            <h3
                className={cn(
                    'font-semibold text-foreground',
                    compact ? 'text-sm' : 'text-base',
                )}
            >
                {title}
            </h3>
            {description && (
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    )
}
