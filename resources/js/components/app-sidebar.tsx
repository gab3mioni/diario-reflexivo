import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Bot, FileText, Folder, LayoutGrid, Settings, Users } from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';
import { dashboard } from '@/routes';

const mainNavItems: NavItem[] = [
    {
        title: 'Painel',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    const page = usePage();
    const auth = page.props.auth as {
        user?: { name: string };
        selectedRole?: string;
        roles?: Array<{ slug: string }>;
    } | undefined;

    const selectedRole = auth?.selectedRole;
    const roles = auth?.roles || [];
    const hasMultipleRoles = roles.length > 1;

    // When user has multiple roles, only show nav items for the selected role
    // When user has a single role, always show that role's items
    const isTeacher = hasMultipleRoles
        ? selectedRole === 'teacher'
        : roles.some(role => role.slug === 'teacher');
    const isStudent = hasMultipleRoles
        ? selectedRole === 'student'
        : roles.some(role => role.slug === 'student');
    const isAdmin = hasMultipleRoles
        ? selectedRole === 'admin'
        : roles.some(role => role.slug === 'admin');

    const teacherNavItems: NavItem[] = isTeacher ? [
        {
            title: 'Alunos',
            href: '/students',
            icon: Users,
        },
        {
            title: 'Aulas',
            href: '/lessons',
            icon: BookOpen,
        },
    ] : [];

    const studentNavItems: NavItem[] = isStudent ? [
        {
            title: 'Minhas Aulas',
            href: '/lessons',
            icon: BookOpen,
        },
    ] : [];

    const adminNavItems: NavItem[] = isAdmin ? [
        {
            title: 'Roteiros',
            href: '/question-scripts',
            icon: FileText,
        },
        {
            title: 'Configuração IA',
            href: '/ai-config',
            icon: Bot,
        },
    ] : [];

    const allNavItems = [...mainNavItems, ...teacherNavItems, ...studentNavItems, ...adminNavItems];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={allNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
