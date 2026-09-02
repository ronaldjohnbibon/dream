<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import PaginationLinks from '@/components/shared/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedPautangOrders, PautangInstallmentStatus, PautangSummary } from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    canManage: boolean;
    view: 'active' | 'paid' | 'overdue';
    pautang: PautangSummary | null;
    pautangOrders: PaginatedPautangOrders | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: props.canManage ? 'Pautang' : 'My pautang', href: route('pautang.index') }]);
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const views = ['active', 'paid', 'overdue'] as const;
const installmentStatusClass = (status: PautangInstallmentStatus) => ({
    pending: 'bg-amber-100 text-amber-800',
    partially_paid: 'bg-sky-100 text-sky-800',
    paid: 'bg-emerald-100 text-emerald-800',
    overdue: 'bg-red-100 text-red-800',
})[status];
const installmentStatusLabel = (status: PautangInstallmentStatus) => ({
    pending: 'Pending',
    partially_paid: 'Partially Paid',
    paid: 'Paid',
    overdue: 'Overdue',
})[status];
</script>

<template>
    <Head :title="canManage ? 'Pautang' : 'My pautang'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ canManage ? 'Pautang' : 'My pautang' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ canManage ? 'Review active, paid, and overdue customer pautang.' : 'Track your current two-give payment schedule.' }}</p>
            </div>

            <template v-if="canManage">
                <div class="flex flex-wrap gap-2">
                    <Button v-for="tab in views" :key="tab" :variant="view === tab ? 'default' : 'outline'" as-child>
                        <Link :href="route('pautang.index', { view: tab })">{{ tab === 'paid' ? 'Fully Paid' : tab[0].toUpperCase() + tab.slice(1) }}</Link>
                    </Button>
                </div>

                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Order amount</th>
                            <th class="px-4 py-3 font-medium">Amount paid</th>
                            <th class="px-4 py-3 font-medium">Remaining</th>
                            <th class="px-4 py-3 font-medium">Next due</th>
                            <th class="px-4 py-3 font-medium">Days overdue</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </template>
                    <template #body>
                        <tr v-if="!pautangOrders || pautangOrders.data.length === 0"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No pautang orders match this view.</td></tr>
                        <tr v-for="order in pautangOrders?.data ?? []" :key="order.id">
                            <td class="px-4 py-3"><p class="font-medium">{{ order.customer?.name }}</p><p class="text-xs text-muted-foreground">{{ order.customer?.mobile_number ?? order.customer?.email ?? 'No contact details' }}</p></td>
                            <td class="px-4 py-3 font-medium">{{ order.order_number }}</td>
                            <td class="px-4 py-3">{{ currency.format(Number(order.order_amount)) }}</td>
                            <td class="px-4 py-3">{{ currency.format(Number(order.amount_paid)) }}</td>
                            <td class="px-4 py-3 font-medium">{{ currency.format(Number(order.remaining_balance)) }}</td>
                            <td class="px-4 py-3">{{ order.next_due_date ?? '—' }}</td>
                            <td class="px-4 py-3">{{ order.days_overdue > 0 ? `${order.days_overdue} day${order.days_overdue === 1 ? '' : 's'}` : '—' }}</td>
                            <td class="px-4 py-3 text-right"><Button size="sm" variant="ghost" as-child><Link :href="route('orders.show', { order: order.id })">View</Link></Button></td>
                        </tr>
                    </template>
                </DataTable>
                <div class="flex justify-end text-sm text-muted-foreground"><PaginationLinks v-if="pautangOrders" :links="pautangOrders.links" /></div>
            </template>

            <template v-else-if="pautang">
                <div class="grid gap-4 sm:grid-cols-3">
                    <Card><CardHeader class="pb-2"><CardDescription>Total balance</CardDescription><CardTitle class="text-2xl">{{ currency.format(Number(pautang.order_amount)) }}</CardTitle></CardHeader></Card>
                    <Card><CardHeader class="pb-2"><CardDescription>Amount paid</CardDescription><CardTitle class="text-2xl">{{ currency.format(Number(pautang.amount_paid)) }}</CardTitle></CardHeader></Card>
                    <Card><CardHeader class="pb-2"><CardDescription>Remaining balance</CardDescription><CardTitle class="text-2xl">{{ currency.format(Number(pautang.remaining_balance)) }}</CardTitle></CardHeader></Card>
                </div>
                <Card>
                    <CardHeader><CardTitle>Current pautang</CardTitle><CardDescription>Order {{ pautang.order_number }}</CardDescription></CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="installment in pautang.installments" :key="installment.id" class="rounded-lg border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3"><p class="font-medium">Give {{ installment.installment_number }}</p><span class="rounded-full px-2 py-1 text-xs" :class="installmentStatusClass(installment.status)">{{ installmentStatusLabel(installment.status) }}</span></div>
                            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-4"><div><p class="text-muted-foreground">Amount due</p><p class="font-medium">{{ currency.format(Number(installment.amount_due)) }}</p></div><div><p class="text-muted-foreground">Due date</p><p class="font-medium">{{ installment.due_date }}</p></div><div><p class="text-muted-foreground">Amount paid</p><p class="font-medium">{{ currency.format(Number(installment.amount_paid)) }}</p></div><div><p class="text-muted-foreground">Remaining</p><p class="font-medium">{{ currency.format(Number(installment.remaining_balance)) }}</p></div></div>
                        </div>
                        <Button variant="outline" as-child><Link :href="route('orders.show', { order: pautang.id })">View order</Link></Button>
                    </CardContent>
                </Card>
            </template>

            <Card v-else>
                <CardHeader><CardTitle>No current pautang</CardTitle><CardDescription>You do not have an active pautang payment schedule.</CardDescription></CardHeader>
            </Card>
        </div>
    </AppLayout>
</template>
