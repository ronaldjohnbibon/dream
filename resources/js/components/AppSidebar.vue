<script setup lang="ts">
import CustomerMobileNav from '@/components/CustomerMobileNav.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';
import type { NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    Bell,
    ChartNoAxesCombined,
    ClipboardList,
    CreditCard,
    HandCoins,
    LayoutGrid,
    MapPin,
    PackageSearch,
    ShoppingCart,
    Truck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const { isMobile } = useSidebar();
const isCustomer = computed(() => page.props.auth.user?.is_admin === false);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [{ title: 'Dashboard', href: route('dashboard'), icon: LayoutGrid }];

    if (page.props.auth.user?.is_admin) {
        items.push({ title: 'Administrators', href: route('users.index'), icon: Users });
        items.push({ title: 'Customers', href: route('customers.index'), icon: Users });
        items.push({ title: 'Rice inventory', href: route('rice-products.index'), icon: PackageSearch });
        items.push({ title: 'Orders', href: route('orders.index'), icon: ShoppingCart });
        items.push({ title: 'Deliveries', href: route('deliveries.index'), icon: Truck });
        items.push({ title: 'Delivery areas', href: route('delivery-areas.index'), icon: MapPin });
        items.push({ title: 'Payments', href: route('gcash-payments.index'), icon: CreditCard });
        items.push({ title: 'Pautang', href: route('pautang.index'), icon: HandCoins });
        items.push({ title: 'Reports', href: route('reports.index'), icon: ChartNoAxesCombined });
        items.push({ title: 'Logs', href: route('activity-logs.index'), icon: ClipboardList });
        items.push({ title: 'Notifications', href: route('notifications.index'), icon: Bell });
    } else {
        items.push({ title: 'My orders', href: route('orders.index'), icon: ShoppingCart });
        items.push({ title: 'My pautang', href: route('pautang.index'), icon: HandCoins });
        items.push({ title: 'My points', href: route('points.show'), icon: Award });
        items.push({ title: 'Notifications', href: route('notifications.index'), icon: Bell });
    }

    return items;
});
</script>

<template>
    <Sidebar v-if="!isCustomer || !isMobile" collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
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
    <CustomerMobileNav v-if="isCustomer" />
    <slot />
</template>
