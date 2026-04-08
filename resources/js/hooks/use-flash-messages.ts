import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useFlashMessages() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: `flash:success:${flash.success}` });
        }
        if (flash?.error) {
            toast.error(flash.error, { id: `flash:error:${flash.error}` });
        }
    }, [flash?.success, flash?.error]);
}
