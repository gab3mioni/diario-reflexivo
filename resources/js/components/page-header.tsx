import * as React from 'react'
import { cn } from '@/lib/utils'

interface PageHeaderProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'title'> {
    title: React.ReactNode
    description?: React.ReactNode
    actions?: React.ReactNode
    icon?: React.ReactNode
}

export function PageHeader({
    title,
    description,
    actions,
    icon,
    className,
    ...props
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 border-b border-border/60 pb-6 sm:flex-row sm:items-start sm:justify-between sm:gap-6',
                className,
            )}
            {...props}
        >
            <div className="flex items-start gap-4 min-w-0">
                {icon && (
                    <div className="shrink-0 rounded-lg bg-muted p-2.5 text-muted-foreground [&>svg]:size-5">
                        {icon}
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <h1 className="truncate font-display text-3xl font-medium tracking-tight text-foreground sm:text-4xl">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1.5 text-sm text-muted-foreground sm:text-base">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    )
}
