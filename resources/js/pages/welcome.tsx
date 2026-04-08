import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import type { Auth } from '@/types';
import { BookOpen, Feather, Sparkles, ArrowRight } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAuthed = !!auth.user;

    return (
        <>
            <Head title="Diário Reflexivo" />
            <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
                {/* Decorative background */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 -z-10"
                >
                    <div className="absolute left-1/2 top-0 h-[520px] w-[900px] -translate-x-1/2 rounded-full bg-gradient-to-br from-[oklch(0.85_0.10_80)] via-[oklch(0.9_0.06_40)] to-transparent opacity-40 blur-3xl dark:opacity-20" />
                    <div className="absolute bottom-0 right-0 h-[360px] w-[600px] translate-x-1/4 translate-y-1/4 rounded-full bg-gradient-to-tr from-[oklch(0.75_0.14_155)] to-transparent opacity-25 blur-3xl dark:opacity-15" />
                    <svg
                        className="absolute inset-0 h-full w-full opacity-[0.025] dark:opacity-[0.04]"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <defs>
                            <pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse">
                                <path d="M 32 0 L 0 0 0 32" fill="none" stroke="currentColor" strokeWidth="1" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>

                {/* Header */}
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <div className="flex items-center gap-2 font-display text-lg font-medium">
                        <Feather className="size-5" aria-hidden="true" />
                        Diário Reflexivo
                    </div>
                    <nav className="flex items-center gap-3">
                        {isAuthed ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center gap-1.5 rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                            >
                                Ir para o painel
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="inline-flex items-center gap-1.5 rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                            >
                                Entrar
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                        )}
                    </nav>
                </header>
            </div>
        </>
    );
}
