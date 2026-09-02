<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import PaginationLinks from '@/components/shared/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    orderStatusLabels,
    paymentStatusLabels,
    paymentTypeLabels,
    type OrderFilters,
    type OrderStatus,
    type PaginatedOrders,
    type PaymentStatus,
    type PaymentType,
} from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    orders: PaginatedOrders;
    filters: OrderFilters;
    canManage: boolean;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: props.canManage ? 'Orders' : 'My orders', href: route('orders.index') }]);
const filters = ref<OrderFilters>({ ...props.filters });
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const statuses = Object.keys(orderStatusLabels) as OrderStatus[];
const paymentTypes = Object.keys(paymentTypeLabels) as PaymentType[];
const paymentStatuses = Object.keys(paymentStatusLabels) as PaymentStatus[];

const applyFilters = () => {
    router.get(route('orders.index'), filters.value, { preserveState: true, replace: true });
};

const orderStatusClass = (status: OrderStatus) => ({
    pending: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-sky-100 text-sky-800',
    preparing: 'bg-violet-100 text-violet-800',
    out_for_delivery: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
})[status];
</script>

<template>
    <Head :title="canManage ? 'Orders' : 'My orders'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ canManage ? 'Orders' : 'My orders' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ canManage ? 'Review customer orders and update their progress.' : 'Track your rice orders and delivery progress.' }}</p>
                </div>
                <Button v-if="!canManage" as-child><Link :href="route('orders.create')">Place order</Link></Button>
            </div>

            <form class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_repeat(3,180px)_auto]" @submit.prevent="applyFilters">
                <Input v-model="filters.search" :placeholder="canManage ? 'Search order, customer, or rice' : 'Search order or rice'" aria-label="Search orders" />
                <select v-model="filters.order_status" class="h-9 rounded-md border border-input bg-background px-3 text-sm"><option value="all">All order statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ orderStatusLabels[status] }}</option></select>
                <select v-model="filters.payment_type" class="h-9 rounded-md border border-input bg-background px-3 text-sm"><option value="all">All payment types</option><option v-for="type in paymentTypes" :key="type" :value="type">{{ paymentTypeLabels[type] }}</option></select>
                <select v-model="filters.payment_status" class="h-9 rounded-md border border-input bg-background px-3 text-sm"><option value="all">All payment statuses</option><option v-for="status in paymentStatuses" :key="status" :value="status">{{ paymentStatusLabels[status] }}</option></select>
                <Button type="submit" variant="outline">Apply filters</Button>
            </form>

            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th v-if="canManage" class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Rice</th>
                        <th class="px-4 py-3 font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Payment</th>
                        <th class="px-4 py-3 font-medium">Order status</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </template>
                <template #body>
                    <tr v-if="orders.data.length === 0"><td :colspan="canManage ? 7 : 6" class="px-4 py-10 text-center text-muted-foreground">No orders match the current filters.</td></tr>
                    <tr v-for="order in orders.data" :key="order.id">
                        <td class="px-4 py-3"><p class="font-medium">{{ order.order_number }}</p><p class="text-xs text-muted-foreground">{{ order.order_date }}</p></td>
                        <td v-if="canManage" class="px-4 py-3">{{ order.customer.name }}</td>
                        <td class="px-4 py-3"><p class="font-medium">{{ order.rice_product.name }}</p><p class="text-xs text-muted-foreground">{{ order.rice_product.brand }} · {{ order.quantity }} sack{{ order.quantity === 1 ? '' : 's' }}</p></td>
                        <td class="px-4 py-3 font-medium">{{ currency.format(Number(order.final_amount)) }}</td>
                        <td class="px-4 py-3"><p>{{ paymentTypeLabels[order.payment_type] }}</p><p class="text-xs text-muted-foreground">{{ paymentStatusLabels[order.payment_status] }}</p></td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs" :class="orderStatusClass(order.order_status)">{{ orderStatusLabels[order.order_status] }}</span></td>
                        <td class="px-4 py-3 text-right"><Button size="sm" variant="ghost" as-child><Link :href="route('orders.show', { order: order.id })">View</Link></Button></td>
                    </tr>
                </template>
            </DataTable>

            <div class="flex justify-end text-sm text-muted-foreground"><PaginationLinks :links="orders.links" /></div>
        </div>
    </AppLayout>
</template>
