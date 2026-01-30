<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, LayoutGrid, FolderGit2, History, BarChart3, Settings } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import RepositoryController from '@/actions/App/Http/Controllers/RepositoryController';
import ReleaseController from '@/actions/App/Http/Controllers/ReleaseController';
import AnalyticsController from '@/actions/App/Http/Controllers/AnalyticsController';
import LaraLedgerSettingsController from '@/actions/App/Http/Controllers/Settings/LaraLedgerSettingsController';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Repositories',
        href: RepositoryController.index().url,
        icon: FolderGit2,
    },
    {
        title: 'Releases',
        href: ReleaseController.index().url,
        icon: History,
    },
    {
        title: 'Analytics',
        href: AnalyticsController.index().url,
        icon: BarChart3,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Settings',
        href: LaraLedgerSettingsController.index().url,
        icon: Settings,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/bmadigan/laraledger',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
