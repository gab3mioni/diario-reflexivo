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
        title: 'Dashboard',
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

    const teacherNavItems: NavItem[] = isTeacher ? [
        {
            title: 'Alunos',
            href: '/teacher/students',
            icon: Users,
        },
    ] : [];

    const allNavItems = [...mainNavItems, ...teacherNavItems];

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
