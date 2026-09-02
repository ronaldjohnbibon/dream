<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { gcashPaymentStatusLabels, type AdminGcashPayment } from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ payment: AdminGcashPayment }>();
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: 'Payments', href: route('gcash-payments.index') }, { title: `Payment #${props.payment.id}`, href: route('gcash-payments.show', { gcashPayment: props.payment.id }) }]);
const approveForm = useForm({ remarks: props.payment.remarks ?? '' });
const rejectForm = useForm({ remarks: props.payment.remarks ?? '' });
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const pending = computed(() => props.payment.status === 'pending_verification');
const approvalError = computed(() => {
    const errors = approveForm.errors as Record<string, string>;

    return errors.amount ?? errors.reference_number ?? errors.payment;
});
const approve = () => approveForm.patch(route('gcash-payments.approve', { gcashPayment: props.payment.id }));
const reject = () => rejectForm.patch(route('gcash-payments.reject', { gcashPayment: props.payment.id }));
</script>

<template>
    <Head :title="`Payment #${payment.id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
            <div class="flex items-center justify-between"><div><h1 class="text-2xl font-semibold tracking-tight">GCash payment #{{ payment.id }}</h1><p class="mt-1 text-sm text-muted-foreground">{{ gcashPaymentStatusLabels[payment.status] }}</p></div><Button variant="outline" as-child><Link :href="route('gcash-payments.index')">Back to payments</Link></Button></div>
            <div class="grid gap-6 lg:grid-cols-2">
                <Card><CardHeader><CardTitle>Payment proof</CardTitle></CardHeader><CardContent class="space-y-4"><img :src="payment.screenshot_url" alt="GCash payment screenshot" class="max-h-[36rem] w-full rounded-lg border object-contain" /><a :href="payment.screenshot_url" target="_blank" class="text-sm text-primary underline">Open full screenshot</a></CardContent></Card>
                <div class="space-y-6">
                    <Card><CardHeader><CardTitle>Submission details</CardTitle></CardHeader><CardContent class="grid gap-4 text-sm sm:grid-cols-2"><div><p class="text-muted-foreground">Customer</p><p class="font-medium">{{ payment.customer.name }}</p><p>{{ payment.customer.mobile_number ?? payment.customer.email }}</p></div><div><p class="text-muted-foreground">Order</p><p class="font-medium">{{ payment.order.order_number }}</p><p v-if="payment.installment">Give {{ payment.installment.installment_number }}</p></div><div><p class="text-muted-foreground">Amount</p><p class="font-medium">{{ currency.format(Number(payment.amount)) }}</p></div><div><p class="text-muted-foreground">Payment date</p><p class="font-medium">{{ payment.payment_date }}</p></div><div class="sm:col-span-2"><p class="text-muted-foreground">GCash reference number</p><p class="font-mono font-medium">{{ payment.reference_number }}</p></div></CardContent></Card>
                    <Card v-if="pending"><CardHeader><CardTitle>Review payment</CardTitle></CardHeader><CardContent class="grid gap-6 md:grid-cols-2"><form class="space-y-4" @submit.prevent="approve"><FormField id="approve-remarks" label="Remarks (optional)" :error="approveForm.errors.remarks"><textarea id="approve-remarks" v-model="approveForm.remarks" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></FormField><p v-if="approvalError" class="text-sm text-destructive">{{ approvalError }}</p><Button type="submit" :disabled="approveForm.processing">Approve payment</Button></form><form class="space-y-4" @submit.prevent="reject"><FormField id="reject-remarks" label="Rejection remarks" :error="rejectForm.errors.remarks" required><textarea id="reject-remarks" v-model="rejectForm.remarks" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required /></FormField><Button type="submit" variant="destructive" :disabled="rejectForm.processing">Reject payment</Button></form></CardContent></Card>
                    <Card v-else><CardHeader><CardTitle>Review outcome</CardTitle></CardHeader><CardContent class="text-sm"><p><span class="text-muted-foreground">Reviewed by:</span> {{ payment.reviewer?.name ?? '—' }}</p><p><span class="text-muted-foreground">Reviewed at:</span> {{ payment.reviewed_at ?? '—' }}</p><p class="mt-3 whitespace-pre-line"><span class="text-muted-foreground">Remarks:</span> {{ payment.remarks ?? 'None' }}</p></CardContent></Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
