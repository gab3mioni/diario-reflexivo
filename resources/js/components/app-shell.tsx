import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from 'sonner';
import { SidebarProvider } from '@/components/ui/sidebar';
import RoleSelectionDialog from '@/components/role-selection-dialog';
import { useFlashMessages } from '@/hooks/use-flash-messages';

type Props = {
    children: ReactNode;
    variant?: 'header' | 'sidebar';
};

export function AppShell({ children, variant = 'header' }: Props) {
    const isOpen = usePage().props.sidebarOpen;
    const { auth } = usePage().props;

    useFlashMessages();

    const shouldShowRoleDialog =
        auth?.user &&
        auth?.hasMultipleRoles &&
        !auth?.selectedRole;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">
                {children}
                {shouldShowRoleDialog && (
                    <RoleSelectionDialog
                        open={true}
                        roles={auth?.roles || []}
                    />
                )}
                <Toaster richColors position="top-right" />
            </div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen}>
            {children}
            {shouldShowRoleDialog && (
                <RoleSelectionDialog
                    open={true}
                    roles={auth?.roles || []}
                />
            )}
            <Toaster richColors position="top-right" />
        </SidebarProvider>
    );
}
