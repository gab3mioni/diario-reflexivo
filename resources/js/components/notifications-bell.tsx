import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEcho } from '@/hooks/use-echo';

type NotificationItem = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    created_at: string;
};

type AuthShape = {
    user: { id: number } | null;
    unread_notifications_count: number;
    recent_notifications: NotificationItem[];
};

export function NotificationsBell() {
    const { auth } = usePage<{ auth: AuthShape }>().props;

    useEcho(
        auth.user ? `App.Models.User.${auth.user.id}` : '',
        '.response-alert.raised',
        () => router.reload({ only: ['auth'] }),
    );

    if (!auth.user) {
        return null;
    }

    const count = auth.unread_notifications_count;
    const high = auth.recent_notifications.some(
        (n) => (n.data as { severity?: string }).severity === 'high',
    );

    return (
        <button
            type="button"
            className="relative inline-flex items-center justify-center rounded-full p-2 hover:bg-muted"
            aria-label={`Notificações (${count})`}
        >
            <Bell className="h-5 w-5" />
            {count > 0 && (
                <span
                    className={`absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-semibold text-white ${
                        high ? 'bg-red-600' : 'bg-blue-600'
                    }`}
                >
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </button>
    );
}
