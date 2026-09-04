<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type MetricFormat = 'currency' | 'number';

interface DashboardMetric {
    label: string;
    value: number;
    format: MetricFormat;
    description: string;
}
interface ChartPoint {
    label: string;
    value: number;
    percentage: number;
}
interface AdminDashboardData {
    metrics: DashboardMetric[];
    charts: {
        daily_sales: ChartPoint[];
        monthly_sales: ChartPoint[];
        collections: ChartPoint[];
        outstanding_balances: ChartPoint[];
        best_selling_rice: ChartPoint[];
        payment_timing: ChartPoint[];
    };
}

type PaymentStatus = 'unpaid' | 'partially_paid' | 'paid' | 'overdue';
type DeliveryStatus = 'pending' | 'scheduled' | 'preparing' | 'out_for_delivery' | 'delivered' | 'failed' | 'cancelled';
type GcashPaymentStatus = 'pending_verification' | 'approved' | 'rejected';
interface CustomerOrderSummary {
    id: number;
    order_number: string;
    rice_product: string;
    quantity: number;
    final_amount: string;
    order_date: string;
    payment_status: PaymentStatus;
    delivery_status: DeliveryStatus | null;
}
interface CustomerPautangSummary extends CustomerOrderSummary {
    amount_paid: string;
    remaining_balance: string;
    next_due_date: string | null;
    payable_installment_id: number | null;
    can_submit_payment: boolean;
    payment_ready_message: string | null;
}
interface CustomerDashboardData {
    points: { balance: number; peso_equivalent: string };
    active_order: CustomerOrderSummary | null;
    active_pautang: CustomerPautangSummary | null;
    recent_orders: CustomerOrderSummary[];
    recent_payments: {
        id: number;
        amount: string;
        payment_date: string;
        status: GcashPaymentStatus;
        order: { id: number; order_number: string };
        installment_number: number | null;
    }[];
    recent_points_transactions: {
        id: number;
        type: string;
        points: number;
        description: string;
        transaction_date: string;
        order: { id: number; order_number: string } | null;
    }[];
}

const props = defineProps<{ dashboard: AdminDashboardData | CustomerDashboardData }>();
const salesPeriod = ref<'daily' | 'monthly'>('daily');
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const number = new Intl.NumberFormat('en-PH');
const dateFormatter = new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' });
const isAdminDashboard = (dashboard: AdminDashboardData | CustomerDashboardData): dashboard is AdminDashboardData => 'metrics' in dashboard;
const adminDashboard = computed<AdminDashboardData | null>(() => (isAdminDashboard(props.dashboard) ? props.dashboard : null));
const customerDashboard = computed<CustomerDashboardData | null>(() => (isAdminDashboard(props.dashboard) ? null : props.dashboard));
const salesPoints = computed(() =>
    salesPeriod.value === 'daily' ? (adminDashboard.value?.charts.daily_sales ?? []) : (adminDashboard.value?.charts.monthly_sales ?? []),
);

const destinations: Record<string, string> = {
    "Today's Orders": route('orders.index'),
    'Pending Payment Verifications': route('gcash-payments.index'),
    'Active Pautang': route('pautang.index'),
    'Outstanding Balance': route('pautang.index'),
    'Overdue Balance': route('pautang.index', { view: 'overdue' }),
    'Available Rice Stock': route('rice-products.index'),
    'Low Stock Products': route('rice-products.index', { low_stock: '1' }),
};

const cardHref = (label: string) => destinations[label] ?? '';
const formatMetric = (metric: DashboardMetric) => (metric.format === 'currency' ? currency.format(metric.value) : number.format(metric.value));
const formatCurrency = (value: number) => currency.format(value);
const formatDate = (value: string) => dateFormatter.format(new Date(`${value}T00:00:00`));
const paymentLabel = (status: PaymentStatus) => ({ unpaid: 'Unpaid', partially_paid: 'Partially paid', paid: 'Paid', overdue: 'Overdue' })[status];
const deliveryLabel = (status: DeliveryStatus | null) =>
    status
        ? {
              pending: 'Pending',
              scheduled: 'Scheduled',
              preparing: 'Preparing',
              out_for_delivery: 'Out for delivery',
              delivered: 'Delivered',
              failed: 'Delivery failed',
              cancelled: 'Cancelled',
          }[status]
        : 'Not available';
const paymentClass = (status: PaymentStatus) =>
    ({
        unpaid: 'bg-amber-100 text-amber-800',
        partially_paid: 'bg-sky-100 text-sky-800',
        paid: 'bg-emerald-100 text-emerald-800',
        overdue: 'bg-red-100 text-red-800',
    })[status];
const deliveryClass = (status: DeliveryStatus | null) =>
    status
        ? {
              pending: 'bg-amber-100 text-amber-800',
              scheduled: 'bg-sky-100 text-sky-800',
              preparing: 'bg-violet-100 text-violet-800',
              out_for_delivery: 'bg-indigo-100 text-indigo-800',
              delivered: 'bg-emerald-100 text-emerald-800',
              failed: 'bg-red-100 text-red-800',
              cancelled: 'bg-slate-100 text-slate-800',
          }[status]
        : 'bg-slate-100 text-slate-800';
const gcashPaymentLabel = (status: GcashPaymentStatus) =>
    ({ pending_verification: 'Pending verification', approved: 'Approved', rejected: 'Rejected' })[status];
const gcashPaymentClass = (status: GcashPaymentStatus) =>
    ({ pending_verification: 'bg-amber-100 text-amber-800', approved: 'bg-emerald-100 text-emerald-800', rejected: 'bg-red-100 text-red-800' })[
        status
    ];
const pointsTypeLabel = (type: string) =>
    ({
        order_reward: 'Completed pautang reward',
        on_time_payment_bonus: 'On-time payment bonus',
        redemption: 'Redemption',
        redemption_refund: 'Redemption refund',
        admin_adjustment: 'Admin adjustment',
    })[type] ?? 'Points transaction';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div v-if="adminDashboard">
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">A live snapshot of sales, payments, customer credit, and rice inventory.</p>
            </div>

            <template v-if="customerDashboard">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Your orders, payments, and points—at a glance.</p>
                    </div>
                    <Button class="w-full sm:w-auto" as-child><Link :href="route('orders.create')">Order rice</Link></Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-2"
                            ><CardDescription>Current points</CardDescription
                            ><CardTitle class="text-3xl">{{ number.format(customerDashboard.points.balance) }}</CardTitle></CardHeader
                        >
                        <CardContent class="text-sm text-muted-foreground"
                            >{{ formatCurrency(Number(customerDashboard.points.peso_equivalent)) }} peso equivalent</CardContent
                        >
                    </Card>
                    <Card>
                        <CardHeader class="pb-2"
                            ><CardDescription>Need help?</CardDescription><CardTitle class="text-lg">Keep track of rewards</CardTitle></CardHeader
                        >
                        <CardContent>
                            <Button class="w-full sm:w-auto" variant="outline" as-child
                                ><Link :href="route('points.show')">View points history</Link></Button
                            >
                        </CardContent>
                    </Card>
                </div>

                <Card v-if="customerDashboard.active_pautang" class="border-amber-300 bg-amber-50/60 dark:bg-amber-950/20">
                    <CardHeader>
                        <CardTitle>Complete your pautang before ordering another on credit</CardTitle>
                        <CardDescription>Fully pay your existing pautang balance before creating another order.</CardDescription>
                    </CardHeader>
                </Card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle>Active order</CardTitle><CardDescription>Your most recently placed unfinished order</CardDescription>
                            </div>
                            <span
                                v-if="customerDashboard.active_order"
                                class="rounded-full px-2 py-1 text-xs"
                                :class="deliveryClass(customerDashboard.active_order.delivery_status)"
                                >{{ deliveryLabel(customerDashboard.active_order.delivery_status) }}</span
                            >
                        </CardHeader>
                        <CardContent v-if="customerDashboard.active_order" class="space-y-4">
                            <div>
                                <p class="font-semibold">{{ customerDashboard.active_order.order_number }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ customerDashboard.active_order.rice_product }} · {{ customerDashboard.active_order.quantity }} sack{{
                                        customerDashboard.active_order.quantity === 1 ? '' : 's'
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <p class="text-muted-foreground">Order total</p>
                                    <p class="font-medium">{{ formatCurrency(Number(customerDashboard.active_order.final_amount)) }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Payment status</p>
                                    <span
                                        class="mt-1 inline-block rounded-full px-2 py-1 text-xs"
                                        :class="paymentClass(customerDashboard.active_order.payment_status)"
                                        >{{ paymentLabel(customerDashboard.active_order.payment_status) }}</span
                                    >
                                </div>
                            </div>
                            <Button size="sm" variant="outline" as-child
                                ><Link :href="route('orders.show', { order: customerDashboard.active_order.id })">View Order</Link></Button
                            >
                        </CardContent>
                        <CardContent v-else class="space-y-4"
                            ><p class="text-sm text-muted-foreground">You do not have an unfinished order right now.</p>
                            <Button size="sm" as-child><Link :href="route('orders.create')">Order Rice</Link></Button></CardContent
                        >
                    </Card>

                    <Card>
                        <CardHeader
                            ><CardTitle>Active pautang</CardTitle
                            ><CardDescription>Your current credit balance and next payment</CardDescription></CardHeader
                        >
                        <CardContent v-if="customerDashboard.active_pautang" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold">{{ customerDashboard.active_pautang.order_number }}</p>
                                <span class="rounded-full px-2 py-1 text-xs" :class="paymentClass(customerDashboard.active_pautang.payment_status)">{{
                                    paymentLabel(customerDashboard.active_pautang.payment_status)
                                }}</span>
                            </div>
                            <div class="grid gap-3 text-sm sm:grid-cols-3">
                                <div>
                                    <p class="text-muted-foreground">Amount paid</p>
                                    <p class="font-medium">{{ formatCurrency(Number(customerDashboard.active_pautang.amount_paid)) }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Remaining</p>
                                    <p class="font-medium">{{ formatCurrency(Number(customerDashboard.active_pautang.remaining_balance)) }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Next due date</p>
                                    <p class="font-medium">
                                        {{
                                            customerDashboard.active_pautang.next_due_date
                                                ? formatDate(customerDashboard.active_pautang.next_due_date)
                                                : 'Not scheduled'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="customerDashboard.active_pautang.payment_ready_message"
                                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                            >
                                {{ customerDashboard.active_pautang.payment_ready_message }}
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button
                                    v-if="
                                        customerDashboard.active_pautang.can_submit_payment && customerDashboard.active_pautang.payable_installment_id
                                    "
                                    class="w-full sm:w-auto"
                                    as-child
                                    ><Link
                                        :href="
                                            route('gcash-payments.create', {
                                                order: customerDashboard.active_pautang.id,
                                                installment: customerDashboard.active_pautang.payable_installment_id,
                                            })
                                        "
                                        >Pay installment</Link
                                    ></Button
                                ><Button class="w-full sm:w-auto" variant="outline" as-child
                                    ><Link :href="route('orders.show', { order: customerDashboard.active_pautang.id })">View order</Link></Button
                                >
                            </div>
                        </CardContent>
                        <CardContent v-else><p class="text-sm text-muted-foreground">You do not have an unpaid pautang balance.</p></CardContent>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <Card>
                        <CardHeader class="flex-row items-center justify-between gap-4 space-y-0"
                            ><div><CardTitle>Recent orders</CardTitle><CardDescription>Your latest five orders</CardDescription></div>
                            <Link class="text-sm text-primary underline" :href="route('orders.index')">View all</Link></CardHeader
                        >
                        <CardContent class="space-y-3"
                            ><p v-if="customerDashboard.recent_orders.length === 0" class="py-4 text-sm text-muted-foreground">No orders yet.</p>
                            <template v-else
                                ><Link
                                    v-for="order in customerDashboard.recent_orders"
                                    :key="order.id"
                                    :href="route('orders.show', { order: order.id })"
                                    class="block rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                    ><div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium">{{ order.order_number }}</p>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                {{ order.rice_product }} · {{ formatDate(order.order_date) }}
                                            </p>
                                        </div>
                                        <span class="text-sm font-medium">{{ formatCurrency(Number(order.final_amount)) }}</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="rounded-full px-2 py-1 text-xs" :class="paymentClass(order.payment_status)">{{
                                            paymentLabel(order.payment_status)
                                        }}</span
                                        ><span class="rounded-full px-2 py-1 text-xs" :class="deliveryClass(order.delivery_status)">{{
                                            deliveryLabel(order.delivery_status)
                                        }}</span>
                                    </div></Link
                                ></template
                            ></CardContent
                        >
                    </Card>

                    <Card>
                        <CardHeader
                            ><CardTitle>Recent payments</CardTitle><CardDescription>Your latest GCash submissions</CardDescription></CardHeader
                        >
                        <CardContent class="space-y-3"
                            ><p v-if="customerDashboard.recent_payments.length === 0" class="py-4 text-sm text-muted-foreground">
                                No payment submissions yet.
                            </p>
                            <template v-else
                                ><Link
                                    v-for="payment in customerDashboard.recent_payments"
                                    :key="payment.id"
                                    :href="route('orders.show', { order: payment.order.id })"
                                    class="block rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                    ><div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium">
                                                {{ payment.order.order_number
                                                }}<span v-if="payment.installment_number"> · Give {{ payment.installment_number }}</span>
                                            </p>
                                            <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(payment.payment_date) }}</p>
                                        </div>
                                        <p class="font-medium">{{ formatCurrency(Number(payment.amount)) }}</p>
                                    </div>
                                    <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs" :class="gcashPaymentClass(payment.status)">{{
                                        gcashPaymentLabel(payment.status)
                                    }}</span></Link
                                ></template
                            ></CardContent
                        >
                    </Card>

                    <Card>
                        <CardHeader class="flex-row items-center justify-between gap-4 space-y-0"
                            ><div><CardTitle>Recent points</CardTitle><CardDescription>Your latest points transactions</CardDescription></div>
                            <Link class="text-sm text-primary underline" :href="route('points.show')">View history</Link></CardHeader
                        >
                        <CardContent class="space-y-3"
                            ><p v-if="customerDashboard.recent_points_transactions.length === 0" class="py-4 text-sm text-muted-foreground">
                                No points transactions yet.
                            </p>
                            <template v-else
                                ><div v-for="entry in customerDashboard.recent_points_transactions" :key="entry.id" class="rounded-lg border p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium">{{ pointsTypeLabel(entry.type) }}</p>
                                            <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(entry.transaction_date) }}</p>
                                        </div>
                                        <p
                                            class="font-semibold"
                                            :class="entry.points > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'"
                                        >
                                            {{ entry.points > 0 ? '+' : '' }}{{ entry.points }}
                                        </p>
                                    </div>
                                    <p class="mt-2 text-xs text-muted-foreground">{{ entry.description }}</p>
                                </div></template
                            ></CardContent
                        >
                    </Card>
                </div>
            </template>

            <template v-else-if="adminDashboard">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <component
                        :is="cardHref(metric.label) ? Link : 'div'"
                        v-for="metric in adminDashboard.metrics"
                        :key="metric.label"
                        :href="cardHref(metric.label) || undefined"
                        class="block"
                    >
                        <Card class="h-full transition-colors" :class="cardHref(metric.label) ? 'hover:bg-muted/40' : ''">
                            <CardHeader class="pb-2"
                                ><CardDescription>{{ metric.label }}</CardDescription
                                ><CardTitle class="text-2xl">{{ formatMetric(metric) }}</CardTitle></CardHeader
                            >
                            <CardContent class="text-sm text-muted-foreground">{{ metric.description }}</CardContent>
                        </Card>
                    </component>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div><CardTitle>Sales trend</CardTitle><CardDescription>Booked, non-cancelled orders</CardDescription></div>
                            <div class="flex rounded-md border p-1 text-xs">
                                <button
                                    class="rounded px-2 py-1"
                                    :class="salesPeriod === 'daily' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                                    @click="salesPeriod = 'daily'"
                                >
                                    Daily
                                </button>
                                <button
                                    class="rounded px-2 py-1"
                                    :class="salesPeriod === 'monthly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                                    @click="salesPeriod = 'monthly'"
                                >
                                    Monthly
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="salesPoints.some((point) => point.value > 0)" class="flex h-56 items-end gap-2" aria-label="Sales trend chart">
                                <div
                                    v-for="point in salesPoints"
                                    :key="point.label"
                                    class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center"
                                >
                                    <span class="text-xs text-muted-foreground">{{ point.value ? formatCurrency(point.value) : '' }}</span>
                                    <div
                                        class="rounded-t bg-primary"
                                        :style="{ height: `${point.percentage}%`, minHeight: point.value ? '4px' : '0' }"
                                        :title="`${point.label}: ${formatCurrency(point.value)}`"
                                    ></div>
                                    <span class="truncate text-xs text-muted-foreground">{{ point.label }}</span>
                                </div>
                            </div>
                            <p v-else class="py-20 text-center text-sm text-muted-foreground">No sales in this period.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            ><CardTitle>Collections</CardTitle
                            ><CardDescription>Approved GCash payments over the last 7 days</CardDescription></CardHeader
                        >
                        <CardContent>
                            <div
                                v-if="adminDashboard.charts.collections.some((point) => point.value > 0)"
                                class="flex h-56 items-end gap-2"
                                aria-label="Collections chart"
                            >
                                <div
                                    v-for="point in adminDashboard.charts.collections"
                                    :key="point.label"
                                    class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center"
                                >
                                    <span class="text-xs text-muted-foreground">{{ point.value ? formatCurrency(point.value) : '' }}</span>
                                    <div
                                        class="rounded-t bg-sky-600"
                                        :style="{ height: `${point.percentage}%`, minHeight: point.value ? '4px' : '0' }"
                                        :title="`${point.label}: ${formatCurrency(point.value)}`"
                                    ></div>
                                    <span class="truncate text-xs text-muted-foreground">{{ point.label }}</span>
                                </div>
                            </div>
                            <p v-else class="py-20 text-center text-sm text-muted-foreground">No approved collections in this period.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Outstanding balances</CardTitle><CardDescription>Unpaid pautang balances</CardDescription></CardHeader>
                        <CardContent class="space-y-5">
                            <div v-for="point in adminDashboard.charts.outstanding_balances" :key="point.label">
                                <div class="mb-2 flex justify-between gap-3 text-sm">
                                    <span>{{ point.label }}</span
                                    ><span class="font-medium">{{ formatCurrency(point.value) }}</span>
                                </div>
                                <div class="h-3 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-rose-500" :style="{ width: `${point.percentage}%` }"></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            ><CardTitle>Best-selling rice</CardTitle><CardDescription>Top five products by ordered sacks</CardDescription></CardHeader
                        >
                        <CardContent v-if="adminDashboard.charts.best_selling_rice.length" class="space-y-4">
                            <div v-for="point in adminDashboard.charts.best_selling_rice" :key="point.label">
                                <div class="mb-2 flex justify-between gap-3 text-sm">
                                    <span class="truncate">{{ point.label }}</span
                                    ><span class="font-medium">{{ number.format(point.value) }} sacks</span>
                                </div>
                                <div class="h-3 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-violet-600" :style="{ width: `${point.percentage}%` }"></div>
                                </div>
                            </div>
                        </CardContent>
                        <CardContent v-else class="py-12 text-center text-sm text-muted-foreground">No product sales yet.</CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            ><CardTitle>On-time vs late payments</CardTitle
                            ><CardDescription>Completed pautang installments</CardDescription></CardHeader
                        >
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div v-for="point in adminDashboard.charts.payment_timing" :key="point.label" class="rounded-lg border p-4">
                                <p class="text-sm text-muted-foreground">{{ point.label }}</p>
                                <p class="mt-1 text-3xl font-semibold">{{ number.format(point.value) }}</p>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :class="point.label === 'On time' ? 'bg-emerald-600' : 'bg-rose-500'"
                                        :style="{ width: `${point.percentage}%` }"
                                    ></div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <Card v-else
                ><CardHeader
                    ><CardTitle>Dashboard unavailable</CardTitle><CardDescription>Please refresh the page and try again.</CardDescription></CardHeader
                ></Card
            >
        </div>
    </AppLayout>
</template>
