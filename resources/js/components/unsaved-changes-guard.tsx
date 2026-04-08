import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';

interface Props {
    dirty: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
}

/**
 * Guards the current page against accidental navigation while there are
 * unsaved changes. Shows a styled confirm dialog for SPA navigation and
 * falls back to the native beforeunload prompt for tab close/reload.
 *
 * Only intercepts GET visits — form submissions (POST/PUT/PATCH/DELETE)
 * pass through, so consumers do not need to combine `dirty` with `!processing`.
 */
export function UnsavedChangesGuard({
    dirty,
    title = 'Descartar alterações?',
    description = 'Você tem alterações não salvas nesta página. Se sair agora, elas serão perdidas.',
    confirmLabel = 'Sair sem salvar',
    cancelLabel = 'Continuar editando',
}: Props) {
    const [pendingHref, setPendingHref] = useState<string | null>(null);
    const dirtyRef = useRef(dirty);
    const bypassRef = useRef(false);

    useEffect(() => {
        dirtyRef.current = dirty;
    }, [dirty]);

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!dirtyRef.current) return;
            event.preventDefault();
            event.returnValue = '';
        };

        const removeInertiaListener = router.on('before', (event) => {
            if (!dirtyRef.current) return;

            if (bypassRef.current) {
                bypassRef.current = false;
                return;
            }

            const visit = event.detail.visit as typeof event.detail.visit & {
                prefetch?: boolean;
                async?: boolean;
            };

            // Ignore non-navigation visits: form submits, prefetch-on-hover,
            // background async visits and partial reloads.
            if (visit.method !== 'get') return;
            if (visit.prefetch) return;
            if (visit.async) return;
            if (Array.isArray(visit.only) && visit.only.length > 0) return;
            if (Array.isArray(visit.except) && visit.except.length > 0) return;

            event.preventDefault();
            setPendingHref(visit.url.href);
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeInertiaListener();
        };
    }, []);

    const handleConfirm = useCallback(() => {
        setPendingHref((href) => {
            if (href) {
                bypassRef.current = true;
                router.visit(href);
            }
            return null;
        });
    }, []);

    const handleOpenChange = useCallback((open: boolean) => {
        if (!open) setPendingHref(null);
    }, []);

    return (
        <ConfirmDialog
            open={pendingHref !== null}
            onOpenChange={handleOpenChange}
            title={title}
            description={description}
            confirmLabel={confirmLabel}
            cancelLabel={cancelLabel}
            tone="destructive"
            onConfirm={handleConfirm}
        />
    );
}
