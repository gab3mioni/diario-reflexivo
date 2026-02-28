import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ChevronDown, Filter, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface FilterOption {
    value: string;
    label: string;
    dot?: string;
}

export interface FilterDefinition {
    key: string;
    label: string;
    placeholder?: string;
    options: FilterOption[];
    width?: string;
}

export interface SearchFilterBarProps {
    search?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    hideSearch?: boolean;
    filters?: FilterDefinition[];
    filterValues?: Record<string, string[]>;
    onFilterChange?: (key: string, values: string[]) => void;
    onFilterClear?: (key: string, value: string) => void;
    onFilterClearAll?: (key: string) => void;
    className?: string;
}

// --- Component ---

export function SearchFilterBar({
    search = '',
    onSearchChange,
    searchPlaceholder = 'Buscar...',
    hideSearch = false,
    filters = [],
    filterValues = {},
    onFilterChange,
    onFilterClear,
    onFilterClearAll,
    className,
}: SearchFilterBarProps) {
    const activeBadges: { filter: FilterDefinition; option: FilterOption }[] = [];
    filters.forEach((filter) => {
        const selected = filterValues[filter.key] ?? [];
        selected.forEach((val) => {
            const option = filter.options.find((o) => o.value === val);
            if (option) {
                activeBadges.push({ filter, option });
            }
        });
    });

    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            {!hideSearch && (
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => onSearchChange?.(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-9"
                    />
                </div>
            )}

            {filters.length > 0 && (
                <div className="flex flex-wrap items-center gap-3">
                    {filters.map((filter) => {
                        const selected = filterValues[filter.key] ?? [];
                        const selectedCount = selected.length;

                        return (
                            <div key={filter.key} className="flex items-center gap-2">
                                <Label className="text-sm font-medium">{filter.label}:</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className={cn('justify-between gap-2 font-normal', filter.width ?? 'w-[200px]')}
                                        >
                                            <span className="truncate">
                                                {selectedCount === 0
                                                    ? (filter.placeholder ?? 'Selecione')
                                                    : selectedCount === 1
                                                        ? filter.options.find((o) => o.value === selected[0])?.label
                                                        : `${selectedCount} selecionados`}
                                            </span>
                                            <ChevronDown className="ml-auto h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[220px] p-2" align="start">
                                        <div className="flex flex-col gap-1 max-h-60 overflow-y-auto">
                                            {filter.options.map((opt) => {
                                                const isChecked = selected.includes(opt.value);

                                                const handleToggle = () => {
                                                    const next = isChecked
                                                        ? selected.filter((v) => v !== opt.value)
                                                        : [...selected, opt.value];
                                                    onFilterChange?.(filter.key, next);
                                                };

                                                return (
                                                    <div
                                                        key={opt.value}
                                                        role="option"
                                                        aria-selected={isChecked}
                                                        tabIndex={0}
                                                        onClick={handleToggle}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter' || e.key === ' ') {
                                                                e.preventDefault();
                                                                handleToggle();
                                                            }
                                                        }}
                                                        className="flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground cursor-pointer select-none"
                                                    >
                                                        <Checkbox
                                                            checked={isChecked}
                                                            tabIndex={-1}
                                                            className="pointer-events-none"
                                                        />
                                                        {opt.dot && (
                                                            <div
                                                                className="h-2 w-2 rounded-full shrink-0"
                                                                style={{ backgroundColor: opt.dot }}
                                                            />
                                                        )}
                                                        <span className="truncate">{opt.label}</span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        {selectedCount > 0 && (
                                            <div className="mt-2 border-t pt-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="w-full justify-center text-xs"
                                                    onClick={() => onFilterClearAll?.(filter.key)}
                                                >
                                                    Limpar seleção
                                                </Button>
                                            </div>
                                        )}
                                    </PopoverContent>
                                </Popover>
                            </div>
                        );
                    })}

                    {activeBadges.map(({ filter, option }) => (
                        <Badge key={`${filter.key}-${option.value}`} variant="secondary" className="flex items-center gap-1">
                            <Filter className="h-3 w-3" />
                            {option.label}
                            <button
                                onClick={() => onFilterClear?.(filter.key, option.value)}
                                className="ml-1 rounded-full p-0.5 hover:bg-secondary-foreground/20"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}
