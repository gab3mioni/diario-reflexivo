import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Users } from 'lucide-react';
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

    const isTeacher = selectedRole === 'teacher' || roles.some(role => role.slug === 'teacher');
    const isStudent = selectedRole === 'student' || roles.some(role => role.slug === 'student');

    const teacherNavItems: NavItem[] = isTeacher ? [
        {
            title: 'Alunos',
            href: '/teacher/students',
            icon: Users,
        },
        {
            title: 'Aulas',
            href: '/teacher/lessons',
            icon: BookOpen,
        },
    ] : [];

    const studentNavItems: NavItem[] = isStudent ? [
        {
            title: 'Minhas Aulas',
            href: '/student/lessons',
            icon: BookOpen,
        },
    ] : [];

    const allNavItems = [...mainNavItems, ...teacherNavItems, ...studentNavItems];

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
