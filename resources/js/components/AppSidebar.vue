<script setup lang="ts">
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
import binders from '@/routes/binders';
import categories from '@/routes/categories';
import documents from '@/routes/documents';
import usersRoutes from '@/routes/users';
import { type AppPageProps, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { FileText, Folder, LayoutGrid, Tags, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<AppPageProps>();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Dokumenty',
            href: documents.index(),
            icon: FileText,
        },
        {
            title: 'Segregatory',
            href: binders.index(),
            icon: Folder,
        },
        {
            title: 'Kategorie',
            href: categories.index(),
            icon: Tags,
        },
    ];

    if (page.props.auth.user?.is_admin) {
        items.push({
            title: 'Uzytkownicy',
            href: usersRoutes.index(),
            icon: Users,
        });
    }

    return items;
});
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
