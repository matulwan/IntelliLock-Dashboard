import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Cctv, ChartNoAxesCombined, FileText, Folder, UserRoundPen, Wifi, Users, Cpu } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: '/overview',
        icon: ChartNoAxesCombined,
    },
    {
        title: 'Access Logs',
        href: '/access-logs',
        icon: FileText,
    },
    {
        title: 'Key Management',
        href: '/key-management',
        icon: Wifi,
    },
    {
        title: 'User Management',
        href: '/user-management',
        icon: UserRoundPen,
    },
    {
        title: 'Security Snaps',
        href: '/security-snaps',
        icon: Cctv,
    },
    {
        title: 'Devices', // Add this line
        href: '/devices',
        icon: Cpu, // Using the Cpu icon for devices
    },
];

const footerNavItems: NavItem[] = [
    // Repository and Documentation links removed
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/overview" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}