import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import type { Auth } from '@/types';
import {
    Bot,
    BookOpen,
    FileText,
    LayoutGrid,
    LogOut,
    Search,
    Settings,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type CommandItem = {
    id: string;
    label: string;
    description?: string;
    icon: React.ComponentType<{ className?: string }>;
    keywords?: string;
    group: 'Navegação' | 'Ações' | 'Conta';
    onSelect: () => void;
};

export function CommandPalette() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [activeIndex, setActiveIndex] = React.useState(0);
    const inputRef = React.useRef<HTMLInputElement>(null);

    const selectedRole = auth.selectedRole;
    const roles = auth.roles ?? [];
    const hasMultipleRoles = roles.length > 1;
    const isTeacher = hasMultipleRoles
        ? selectedRole === 'teacher'
        : roles.some((r) => r.slug === 'teacher');
    const isStudent = hasMultipleRoles
        ? selectedRole === 'student'
        : roles.some((r) => r.slug === 'student');
    const isAdmin = hasMultipleRoles
        ? selectedRole === 'admin'
        : roles.some((r) => r.slug === 'admin');

    const go = React.useCallback(
        (url: string) => {
            setOpen(false);
            setTimeout(() => router.visit(url), 50);
        },
        [],
    );

    const items: CommandItem[] = React.useMemo(() => {
        const list: CommandItem[] = [
            {
                id: 'dashboard',
                label: 'Painel',
                icon: LayoutGrid,
                group: 'Navegação',
                keywords: 'home inicio',
                onSelect: () => go('/dashboard'),
            },
        ];

        if (isTeacher) {
            list.push(
                {
                    id: 'teacher-lessons',
                    label: 'Aulas',
                    description: 'Gerenciar aulas',
                    icon: BookOpen,
                    group: 'Navegação',
                    onSelect: () => go('/lessons'),
                },
                {
                    id: 'teacher-students',
                    label: 'Alunos',
                    description: 'Ver lista de alunos',
                    icon: Users,
                    group: 'Navegação',
                    onSelect: () => go('/students'),
                },
            );
        }

        if (isStudent) {
            list.push({
                id: 'student-lessons',
                label: 'Minhas aulas',
                description: 'Ver e responder diários',
                icon: BookOpen,
                group: 'Navegação',
                onSelect: () => go('/lessons'),
            });
        }

        if (isAdmin) {
            list.push(
                {
                    id: 'admin-scripts',
                    label: 'Roteiros de perguntas',
                    icon: FileText,
                    group: 'Navegação',
                    onSelect: () => go('/question-scripts'),
                },
                {
                    id: 'admin-ai',
                    label: 'Configuração IA',
                    description: 'Provedor e prompts',
                    icon: Bot,
                    group: 'Navegação',
                    onSelect: () => go('/ai-config'),
                },
            );
        }

        list.push(
            {
                id: 'settings',
                label: 'Configurações',
                icon: Settings,
                group: 'Conta',
                onSelect: () => go('/settings/profile'),
            },
            {
                id: 'logout',
                label: 'Sair',
                icon: LogOut,
                group: 'Conta',
                onSelect: () => {
                    setOpen(false);
                    router.post('/logout');
                },
            },
        );

        return list;
    }, [isTeacher, isStudent, isAdmin, go]);

    const filtered = React.useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return items;
        return items.filter((item) => {
            const hay = `${item.label} ${item.description ?? ''} ${item.keywords ?? ''}`.toLowerCase();
            return hay.includes(q);
        });
    }, [items, query]);

    const groups = React.useMemo(() => {
        const map = new Map<string, CommandItem[]>();
        filtered.forEach((item) => {
            const arr = map.get(item.group) ?? [];
            arr.push(item);
            map.set(item.group, arr);
        });
        return Array.from(map.entries());
    }, [filtered]);

    // Global shortcut
    React.useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((v) => !v);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Reset state on open
    React.useEffect(() => {
        if (open) {
            setQuery('');
            setActiveIndex(0);
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    React.useEffect(() => {
        setActiveIndex(0);
    }, [query]);

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex((i) => Math.min(i + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            filtered[activeIndex]?.onSelect();
        }
    };

    if (!auth.user) return null;

    let runningIndex = 0;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
                <DialogTitle className="sr-only">Paleta de comandos</DialogTitle>
                <DialogDescription className="sr-only">
                    Busque e navegue rapidamente pela aplicação
                </DialogDescription>
                <div className="flex items-center gap-2 border-b border-border/60 px-4">
                    <Search className="size-4 text-muted-foreground" aria-hidden="true" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Digite um comando ou busque..."
                        className="flex-1 bg-transparent py-4 text-sm outline-none placeholder:text-muted-foreground"
                        aria-label="Buscar comandos"
                    />
                    <kbd className="hidden rounded border border-border/60 bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground sm:inline-block">
                        ESC
                    </kbd>
                </div>
                <div className="max-h-[420px] overflow-y-auto py-2">
                    {filtered.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                            Nenhum resultado para "{query}".
                        </p>
                    ) : (
                        groups.map(([group, groupItems]) => (
                            <div key={group} className="px-2 pb-2">
                                <div className="px-2 py-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    {group}
                                </div>
                                {groupItems.map((item) => {
                                    const thisIndex = runningIndex++;
                                    const isActive = thisIndex === activeIndex;
                                    const Icon = item.icon;
                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onMouseEnter={() => setActiveIndex(thisIndex)}
                                            onClick={item.onSelect}
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-md px-2 py-2 text-left text-sm transition-colors',
                                                isActive ? 'bg-muted text-foreground' : 'text-muted-foreground',
                                            )}
                                        >
                                            <Icon className="size-4 shrink-0" aria-hidden="true" />
                                            <span className="flex-1">
                                                <span className="text-foreground">{item.label}</span>
                                                {item.description && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {item.description}
                                                    </span>
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        ))
                    )}
                </div>
                <div className="flex items-center justify-between border-t border-border/60 bg-muted/30 px-4 py-2 text-[11px] text-muted-foreground">
                    <span className="flex items-center gap-3">
                        <span>
                            <kbd className="rounded border border-border/60 bg-background px-1">↑↓</kbd> navegar
                        </span>
                        <span>
                            <kbd className="rounded border border-border/60 bg-background px-1">↵</kbd> selecionar
                        </span>
                    </span>
                    <span>
                        <kbd className="rounded border border-border/60 bg-background px-1">⌘K</kbd> abrir
                    </span>
                </div>
            </DialogContent>
        </Dialog>
    );
}
