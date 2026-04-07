import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftRight, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import type { User } from '@/types';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

type Props = {
    user: User;
};

const roleLabels: Record<string, string> = {
    student: 'Aluno',
    teacher: 'Professor',
    admin: 'Administrador',
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth } = usePage().props;
    const roles = auth?.roles || [];
    const selectedRole = auth?.selectedRole;
    const hasMultipleRoles = auth?.hasMultipleRoles;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const handleSwitchRole = (roleSlug: string) => {
        cleanup();
        router.post('/select-role', { role: roleSlug });
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Configurações
                    </Link>
                </DropdownMenuItem>
                {hasMultipleRoles && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <ArrowLeftRight className="mr-2 h-4 w-4" />
                            <span>Trocar acesso</span>
                            {selectedRole && (
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {roleLabels[selectedRole] ?? selectedRole}
                                </span>
                            )}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            {roles.map((role) => (
                                <DropdownMenuItem
                                    key={role.id}
                                    onClick={() => handleSwitchRole(role.slug)}
                                    className="cursor-pointer"
                                    disabled={role.slug === selectedRole}
                                >
                                    <span className={role.slug === selectedRole ? 'font-semibold' : ''}>
                                        {role.display_name}
                                    </span>
                                    {role.slug === selectedRole && (
                                        <span className="ml-auto text-xs text-muted-foreground">atual</span>
                                    )}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                )}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Sair
                </Link>
            </DropdownMenuItem>
        </>
    );
}
