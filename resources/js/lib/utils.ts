import type { InertiaLinkProps } from '@inertiajs/react';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { type ColumnDef } from '@tanstack/react-table'

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

type ColumnKeys<T> = ColumnDef<T> & { accessorKey?: string; header?: string }

export function onlyColumns<T>(accessorKeyList: string[], allColumns: ColumnDef<T>[]): ColumnDef<T>[] {
    return allColumns.filter((col) => {
        const c = col as ColumnKeys<T>
        return accessorKeyList.includes(c.accessorKey ?? '') || accessorKeyList.includes(c.header ?? '')
    })
}

export function excludeColumns<T>(accessorKeyList: string[], allColumns: ColumnDef<T>[]): ColumnDef<T>[] {
    return allColumns.filter((col) => {
        const c = col as ColumnKeys<T>
        return !(accessorKeyList.includes(c.accessorKey ?? '') || accessorKeyList.includes(c.header ?? ''))
    })
}

export function formatDateTime(date: string | Date): string {
    const d = new Date(date)
    return d.toLocaleDateString('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

export function formatDate(date: string | Date): string {
    const d = new Date(date)
    return d.toLocaleDateString('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    })
}