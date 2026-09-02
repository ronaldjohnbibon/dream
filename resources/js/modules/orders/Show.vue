<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    orderStatusLabels,
    paymentStatusLabels,
    paymentTypeLabels,
    type Order,
    type OrderStatus,
    type PautangInstallmentStatus,
    type PaymentStatus,
} from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    order: Order;
    canManage: boolean;
    allowedStatuses: OrderStatus[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.canManage ? 'Orders' : 'My orders', href: route('orders.index') },
    { title: props.order.order_number, href: route('orders.show', { order: props.order.id }) },
]);
const form = useForm({
    order_status: props.order.order_status,
    payment_status: props.order.payment_status,
    delivery_date: props.order.delivery_date ?? '',
});
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const paymentStatuses = Object.keys(paymentStatusLabels) as PaymentStatus[];
const isPautang = computed(() => props.order.payment_type === 'pautang');
const paymentAmounts = reactive<Record<number, string>>({});
const paymentErrors = reactive<Record<number, string>>({});
const payingInstallmentId = ref<number | null>(null);
watch(
    () => props.order.pautang_installments,
    (installments) => {
        for (const installment of installments) {
            if (paymentAmounts[installment.id] === undefined) {
                paymentAmounts[installment.id] = '';
            }
        }
    },
    { immediate: true },
);
const orderStatusClass = computed(() => ({
    pending: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-sky-100 text-sky-800',
    preparing: 'bg-violet-100 text-violet-800',
    out_for_delivery: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
})[props.order.order_status]);

const updateOrder = () => {
    form.patch(route('orders.update', { order: props.order.id }));
};
const recordPayment = (installmentId: number) => {
    payingInstallmentId.value = installmentId;
    paymentErrors[installmentId] = '';
    router.patch(
        route('pautang-installments.payment', { pautangInstallment: installmentId }),
        { amount: paymentAmounts[installmentId] },
        {
            preserveScroll: true,
            onError: (errors) => {
                paymentErrors[installmentId] = errors.amount ?? 'Unable to record this payment.';
            },
            onFinish: () => {
                payingInstallmentId.value = null;
            },
        },
    );
};
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
    <Head :title="order.order_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-3"><h1 class="text-2xl font-semibold tracking-tight">{{ order.order_number }}</h1><span class="rounded-full px-2 py-1 text-xs" :class="orderStatusClass">{{ orderStatusLabels[order.order_status] }}</span></div>
                    <p class="mt-1 text-sm text-muted-foreground">Ordered on {{ order.order_date }}</p>
                </div>
                <Button variant="outline" as-child><Link :href="route('orders.index')">Back to orders</Link></Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Final amount</CardTitle></CardHeader><CardContent><p class="text-2xl font-semibold">{{ currency.format(Number(order.final_amount)) }}</p></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Payment</CardTitle></CardHeader><CardContent><p class="text-2xl font-semibold">{{ paymentTypeLabels[order.payment_type] }}</p><p class="mt-1 text-sm text-muted-foreground">{{ paymentStatusLabels[order.payment_status] }}</p></CardContent></Card>
                <Card><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Delivery date</CardTitle></CardHeader><CardContent><p class="text-2xl font-semibold">{{ order.delivery_date ?? 'Not scheduled' }}</p></CardContent></Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Order details</CardTitle></CardHeader>
                    <CardContent class="grid gap-5 text-sm sm:grid-cols-2">
                        <div><p class="text-muted-foreground">Rice product</p><p class="mt-1 font-medium">{{ order.rice_product.name }}</p><p class="text-muted-foreground">{{ order.rice_product.brand }} · {{ order.rice_product.sack_size }}kg sack</p></div>
                        <div><p class="text-muted-foreground">Quantity</p><p class="mt-1 font-medium">{{ order.quantity }} sack{{ order.quantity === 1 ? '' : 's' }}</p></div>
                        <div><p class="text-muted-foreground">Unit price</p><p class="mt-1 font-medium">{{ currency.format(Number(order.unit_price)) }}</p></div>
                        <div><p class="text-muted-foreground">Subtotal</p><p class="mt-1 font-medium">{{ currency.format(Number(order.subtotal)) }}</p></div>
                        <div><p class="text-muted-foreground">Points used</p><p class="mt-1 font-medium">{{ order.points_used }}</p></div>
                        <div><p class="text-muted-foreground">Points discount</p><p class="mt-1 font-medium">{{ currency.format(Number(order.points_discount)) }}</p></div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Delivery details</CardTitle></CardHeader>
                    <CardContent class="grid gap-5 text-sm">
                        <div v-if="canManage"><p class="text-muted-foreground">Customer</p><p class="mt-1 font-medium">{{ order.customer.name }}</p><p class="text-muted-foreground">{{ order.customer.mobile_number ?? order.customer.email ?? 'No contact details' }}</p></div>
                        <div><p class="text-muted-foreground">Delivery area</p><p class="mt-1 font-medium">{{ order.delivery_area }}</p></div>
                        <div><p class="text-muted-foreground">Delivery address</p><p class="mt-1 whitespace-pre-line font-medium">{{ order.delivery_address }}</p></div>
                        <div v-if="order.notes"><p class="text-muted-foreground">Notes</p><p class="mt-1 whitespace-pre-line font-medium">{{ order.notes }}</p></div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="isPautang && order.pautang_installments.length > 0">
                <CardHeader><CardTitle>Pautang installments</CardTitle></CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="installment in order.pautang_installments" :key="installment.id" class="rounded-lg border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3"><p class="font-medium">Give {{ installment.installment_number }}</p><span class="rounded-full px-2 py-1 text-xs" :class="installmentStatusClass(installment.status)">{{ installmentStatusLabel(installment.status) }}</span></div>
                        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-5">
                            <div><p class="text-muted-foreground">Amount due</p><p class="font-medium">{{ currency.format(Number(installment.amount_due)) }}</p></div>
                            <div><p class="text-muted-foreground">Due date</p><p class="font-medium">{{ installment.due_date }}</p></div>
                            <div><p class="text-muted-foreground">Amount paid</p><p class="font-medium">{{ currency.format(Number(installment.amount_paid)) }}</p></div>
                            <div><p class="text-muted-foreground">Remaining</p><p class="font-medium">{{ currency.format(Number(installment.remaining_balance)) }}</p></div>
                            <div><p class="text-muted-foreground">Paid date</p><p class="font-medium">{{ installment.paid_date ?? '—' }}</p></div>
                        </div>
                        <form v-if="canManage && Number(installment.remaining_balance) > 0" class="mt-4 flex max-w-md gap-2" @submit.prevent="recordPayment(installment.id)">
                            <Input v-model="paymentAmounts[installment.id]" type="number" min="0.01" :max="installment.remaining_balance" step="0.01" placeholder="Payment amount" required />
                            <Button type="submit" :disabled="payingInstallmentId === installment.id">Record payment</Button>
                        </form>
                        <p v-if="canManage && paymentErrors[installment.id]" class="mt-2 text-sm text-destructive">{{ paymentErrors[installment.id] }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="canManage">
                <CardHeader><CardTitle>Manage order</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-5 md:grid-cols-3" @submit.prevent="updateOrder">
                        <FormField id="order-status" label="Order status" :error="form.errors.order_status" required>
                            <select id="order-status" v-model="form.order_status" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option v-for="status in allowedStatuses" :key="status" :value="status">{{ orderStatusLabels[status] }}</option></select>
                        </FormField>
                        <FormField v-if="!isPautang" id="payment-status" label="Payment status" :error="form.errors.payment_status" required>
                            <select id="payment-status" v-model="form.payment_status" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option v-for="status in paymentStatuses" :key="status" :value="status">{{ paymentStatusLabels[status] }}</option></select>
                        </FormField>
                        <FormField id="delivery-date" label="Delivery date" :error="form.errors.delivery_date">
                            <input id="delivery-date" v-model="form.delivery_date" type="date" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        </FormField>
                        <div class="md:col-span-3"><Button type="submit" :disabled="form.processing">Save changes</Button></div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
