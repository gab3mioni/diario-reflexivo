import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import RoleSelectionDialog from '@/components/role-selection-dialog';

type Props = {
    children: ReactNode;
    variant?: 'header' | 'sidebar';
};

export function AppShell({ children, variant = 'header' }: Props) {
    const isOpen = usePage().props.sidebarOpen;
    const { auth } = usePage().props;

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
        </SidebarProvider>
    );
}
