<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type MetricFormat = 'currency' | 'number';

interface DashboardMetric { label: string; value: number; format: MetricFormat; description: string; }
interface ChartPoint { label: string; value: number; percentage: number; }
interface CashPautangPoint { label: string; cash: number; pautang: number; cash_percentage: number; pautang_percentage: number; }
interface DashboardData {
    metrics: DashboardMetric[];
    charts: {
        daily_sales: ChartPoint[];
        monthly_sales: ChartPoint[];
        cash_vs_pautang: CashPautangPoint[];
        collections: ChartPoint[];
        outstanding_balances: ChartPoint[];
        best_selling_rice: ChartPoint[];
        payment_timing: ChartPoint[];
    };
}

const props = defineProps<{ dashboard: DashboardData | null }>();
const salesPeriod = ref<'daily' | 'monthly'>('daily');
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const number = new Intl.NumberFormat('en-PH');
const salesPoints = computed(() => (salesPeriod.value === 'daily' ? props.dashboard?.charts.daily_sales ?? [] : props.dashboard?.charts.monthly_sales ?? []));

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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">A live snapshot of sales, payments, customer credit, and rice inventory.</p>
            </div>

            <template v-if="dashboard">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <component :is="cardHref(metric.label) ? Link : 'div'" v-for="metric in dashboard.metrics" :key="metric.label" :href="cardHref(metric.label) || undefined" class="block">
                        <Card class="h-full transition-colors" :class="cardHref(metric.label) ? 'hover:bg-muted/40' : ''">
                            <CardHeader class="pb-2"><CardDescription>{{ metric.label }}</CardDescription><CardTitle class="text-2xl">{{ formatMetric(metric) }}</CardTitle></CardHeader>
                            <CardContent class="text-sm text-muted-foreground">{{ metric.description }}</CardContent>
                        </Card>
                    </component>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div><CardTitle>Sales trend</CardTitle><CardDescription>Booked, non-cancelled orders</CardDescription></div>
                            <div class="flex rounded-md border p-1 text-xs">
                                <button class="rounded px-2 py-1" :class="salesPeriod === 'daily' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="salesPeriod = 'daily'">Daily</button>
                                <button class="rounded px-2 py-1" :class="salesPeriod === 'monthly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="salesPeriod = 'monthly'">Monthly</button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="salesPoints.some((point) => point.value > 0)" class="flex h-56 items-end gap-2" aria-label="Sales trend chart">
                                <div v-for="point in salesPoints" :key="point.label" class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                                    <span class="text-xs text-muted-foreground">{{ point.value ? formatCurrency(point.value) : '' }}</span>
                                    <div class="rounded-t bg-primary" :style="{ height: `${point.percentage}%`, minHeight: point.value ? '4px' : '0' }" :title="`${point.label}: ${formatCurrency(point.value)}`"></div>
                                    <span class="truncate text-xs text-muted-foreground">{{ point.label }}</span>
                                </div>
                            </div>
                            <p v-else class="py-20 text-center text-sm text-muted-foreground">No sales in this period.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Cash vs Pautang</CardTitle><CardDescription>Booked sales over the last 7 days</CardDescription></CardHeader>
                        <CardContent>
                            <div v-if="dashboard.charts.cash_vs_pautang.some((point) => point.cash + point.pautang > 0)" class="flex h-56 items-end gap-2" aria-label="Cash versus pautang sales chart">
                                <div v-for="point in dashboard.charts.cash_vs_pautang" :key="point.label" class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                                    <div class="flex h-full flex-col justify-end overflow-hidden rounded-t" :title="`${point.label}: Cash ${formatCurrency(point.cash)}, Pautang ${formatCurrency(point.pautang)}`">
                                        <div class="bg-amber-500" :style="{ height: `${point.pautang_percentage}%` }"></div>
                                        <div class="bg-emerald-600" :style="{ height: `${point.cash_percentage}%` }"></div>
                                    </div>
                                    <span class="truncate text-xs text-muted-foreground">{{ point.label }}</span>
                                </div>
                            </div>
                            <p v-else class="py-20 text-center text-sm text-muted-foreground">No sales in this period.</p>
                            <div class="mt-3 flex gap-4 text-xs text-muted-foreground"><span><i class="mr-1 inline-block size-2 rounded-sm bg-emerald-600"></i>Cash</span><span><i class="mr-1 inline-block size-2 rounded-sm bg-amber-500"></i>Pautang</span></div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Collections</CardTitle><CardDescription>Approved GCash payments over the last 7 days</CardDescription></CardHeader>
                        <CardContent>
                            <div v-if="dashboard.charts.collections.some((point) => point.value > 0)" class="flex h-56 items-end gap-2" aria-label="Collections chart">
                                <div v-for="point in dashboard.charts.collections" :key="point.label" class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                                    <span class="text-xs text-muted-foreground">{{ point.value ? formatCurrency(point.value) : '' }}</span>
                                    <div class="rounded-t bg-sky-600" :style="{ height: `${point.percentage}%`, minHeight: point.value ? '4px' : '0' }" :title="`${point.label}: ${formatCurrency(point.value)}`"></div>
                                    <span class="truncate text-xs text-muted-foreground">{{ point.label }}</span>
                                </div>
                            </div>
                            <p v-else class="py-20 text-center text-sm text-muted-foreground">No approved collections in this period.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Outstanding balances</CardTitle><CardDescription>Unpaid balances by payment type</CardDescription></CardHeader>
                        <CardContent class="space-y-5">
                            <div v-for="point in dashboard.charts.outstanding_balances" :key="point.label">
                                <div class="mb-2 flex justify-between gap-3 text-sm"><span>{{ point.label }}</span><span class="font-medium">{{ formatCurrency(point.value) }}</span></div>
                                <div class="h-3 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-rose-500" :style="{ width: `${point.percentage}%` }"></div></div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Best-selling rice</CardTitle><CardDescription>Top five products by ordered sacks</CardDescription></CardHeader>
                        <CardContent v-if="dashboard.charts.best_selling_rice.length" class="space-y-4">
                            <div v-for="point in dashboard.charts.best_selling_rice" :key="point.label">
                                <div class="mb-2 flex justify-between gap-3 text-sm"><span class="truncate">{{ point.label }}</span><span class="font-medium">{{ number.format(point.value) }} sacks</span></div>
                                <div class="h-3 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-violet-600" :style="{ width: `${point.percentage}%` }"></div></div>
                            </div>
                        </CardContent>
                        <CardContent v-else class="py-12 text-center text-sm text-muted-foreground">No product sales yet.</CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>On-time vs late payments</CardTitle><CardDescription>Completed pautang installments</CardDescription></CardHeader>
                        <CardContent class="grid gap-4 sm:grid-cols-2">
                            <div v-for="point in dashboard.charts.payment_timing" :key="point.label" class="rounded-lg border p-4">
                                <p class="text-sm text-muted-foreground">{{ point.label }}</p><p class="mt-1 text-3xl font-semibold">{{ number.format(point.value) }}</p>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full" :class="point.label === 'On time' ? 'bg-emerald-600' : 'bg-rose-500'" :style="{ width: `${point.percentage}%` }"></div></div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <Card v-else><CardHeader><CardTitle>Your account is ready</CardTitle><CardDescription>Use Settings to update your profile or password.</CardDescription></CardHeader></Card>
        </div>
    </AppLayout>
</template>
