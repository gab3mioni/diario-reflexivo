import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    useReactTable,
} from "@tanstack/react-table";

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    initialPageSize?: number;
}

export function DataTable<TData, TValue>({
                                             columns,
                                             data,
                                             initialPageSize = 10,
                                         }: DataTableProps<TData, TValue>) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: {
            pagination: {pageIndex: 0, pageSize: initialPageSize},
        },
    });

    const {pageIndex, pageSize} = table.getState().pagination;

    return (
        <div className="rounded-xl border border-border/60 shadow-sm bg-card overflow-hidden">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead
                                    key={header.id}
                                    className="bg-secondary/30 text-secondary-foreground font-semibold text-xs uppercase tracking-wider"
                                >
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                            header.column.columnDef.header,
                                            header.getContext()
                                        )}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>

                <TableBody>
                    {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={row.getIsSelected() && "selected"}
                                className="hover:bg-secondary/20 transition-all duration-200"
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id} className="text-sm">
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-32 text-center text-muted-foreground"
                            >
                                <div className="flex flex-col items-center justify-center gap-2">
                                    <svg
                                        className="w-12 h-12 text-muted-foreground/40"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={1.5}
                                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <p className="text-sm font-medium">Nenhum resultado encontrado.</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>

            <div className="flex items-center justify-between px-4 py-3 border-t border-border/60 bg-muted/30">
                <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">Mostrar</span>
                    <select
                        className="h-9 px-3 rounded-lg border border-border bg-background text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer"
                        value={pageSize}
                        onChange={(e) => table.setPageSize(Number(e.target.value))}
                    >
                        {[10, 25, 50, 100].map((size) => (
                            <option key={size} value={size}>
                                {size}
                            </option>
                        ))}
                    </select>
                    <span className="text-sm text-muted-foreground">por página</span>
                </div>

                <div className="flex items-center gap-6">
                    <div className="text-sm text-muted-foreground">
                        Página <span className="font-semibold text-foreground">{pageIndex + 1}</span> de{" "}
                        <span className="font-semibold text-foreground">{table.getPageCount()}</span>
                    </div>

                    <div className="flex items-center gap-1">
                        <button
                            className="h-9 w-9 flex items-center justify-center rounded-lg border border-border bg-background hover:bg-secondary/20 hover:border-primary/30 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:border-border transition-all duration-200"
                            onClick={() => table.setPageIndex(0)}
                            disabled={!table.getCanPreviousPage()}
                            aria-label="Primeira página"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
                                />
                            </svg>
                        </button>
                        <button
                            className="h-9 w-9 flex items-center justify-center rounded-lg border border-border bg-background hover:bg-secondary/20 hover:border-primary/30 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:border-border transition-all duration-200"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            aria-label="Página anterior"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </button>
                        <button
                            className="h-9 w-9 flex items-center justify-center rounded-lg border border-border bg-background hover:bg-secondary/20 hover:border-primary/30 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:border-border transition-all duration-200"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            aria-label="Próxima página"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </button>
                        <button
                            className="h-9 w-9 flex items-center justify-center rounded-lg border border-border bg-background hover:bg-secondary/20 hover:border-primary/30 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:border-border transition-all duration-200"
                            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                            disabled={!table.getCanNextPage()}
                            aria-label="Última página"
                        >
                            <svg
                                className="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 5l7 7-7 7M5 5l7 7-7 7"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}