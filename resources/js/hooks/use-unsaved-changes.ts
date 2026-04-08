import { router } from '@inertiajs/react'
import { useEffect } from 'react'

/**
 * Warn the user about unsaved changes before they navigate away
 * (via Inertia link or browser back/forward/close).
 *
 * @param dirty whether the form currently has unsaved changes
 * @param message message shown in the browser prompt
 */
export function useUnsavedChanges(
    dirty: boolean,
    message = 'Você tem alterações não salvas. Deseja realmente sair?',
) {
    useEffect(() => {
        if (!dirty) return

        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            e.preventDefault()
            e.returnValue = message
            return message
        }

        const removeInertiaListener = router.on('before', (event) => {
            if (!confirm(message)) {
                event.preventDefault()
            }
        })

        window.addEventListener('beforeunload', handleBeforeUnload)

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload)
            removeInertiaListener()
        }
    }, [dirty, message])
}
