<script setup lang="ts">
import ConfirmModal from '@/components/shared/ConfirmModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Customer, CustomerStatus, CustomerSummary } from '@/modules/customers/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    customer: Customer;
    summary: CustomerSummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: route('customers.index') },
    { title: props.customer.name, href: route('customers.show', { customer: props.customer.id }) },
];

const suspending = ref(false);
const suspendDialogOpen = ref(false);
const reactivating = ref(false);
const formattedDate = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
const statusLabel = computed(() => ({
    good_standing: 'Good Standing',
    overdue: 'Overdue',
    suspended: 'Suspended',
})[props.customer.account_status as CustomerStatus]);
const statusClass = computed(() => ({
    good_standing: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    overdue: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
})[props.customer.account_status as CustomerStatus]);

const suspendCustomer = () => {
    suspending.value = true;
    router.patch(route('customers.suspend', { customer: props.customer.id }), {}, {
        onFinish: () => {
            suspending.value = false;
        },
        onSuccess: () => {
            suspendDialogOpen.value = false;
        },
    });
};

const reactivateCustomer = () => {
    reactivating.value = true;
    router.patch(route('customers.reactivate', { customer: props.customer.id }), {}, {
        onFinish: () => {
            reactivating.value = false;
        },
    });
};
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold">{{ customer.name }}</h1>
                        <span class="rounded-full px-2 py-1 text-xs" :class="statusClass">{{ statusLabel }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">Customer profile</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link :href="route('customers.points.show', { customer: customer.id })">View points</Link></Button>
                    <Button v-if="customer.account_status !== 'suspended'" variant="destructive" @click="suspendDialogOpen = true">Suspend customer</Button>
                    <Button v-else variant="outline" :disabled="reactivating" @click="reactivateCustomer">Reactivate customer</Button>
                    <Button as-child><Link :href="route('customers.edit', { customer: customer.id })">Edit customer</Link></Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Customer information</CardTitle></CardHeader>
                <CardContent class="grid gap-5 text-sm sm:grid-cols-2">
                    <div><p class="text-muted-foreground">Email</p><p class="mt-1 font-medium">{{ customer.email || 'Not provided' }}</p></div>
                    <div><p class="text-muted-foreground">Mobile number</p><p class="mt-1 font-medium">{{ customer.mobile_number }}</p></div>
                    <div><p class="text-muted-foreground">Delivery area</p><p class="mt-1 font-medium">{{ customer.delivery_area }}</p></div>
                    <div><p class="text-muted-foreground">Account status</p><p class="mt-1 font-medium">{{ statusLabel }}</p></div>
                    <div class="sm:col-span-2"><p class="text-muted-foreground">Complete address</p><p class="mt-1 whitespace-pre-line font-medium">{{ customer.complete_address }}</p></div>
                    <div><p class="text-muted-foreground">Created</p><p class="mt-1 font-medium">{{ formattedDate.format(new Date(customer.created_at)) }}</p></div>
                    <div><p class="text-muted-foreground">Last updated</p><p class="mt-1 font-medium">{{ formattedDate.format(new Date(customer.updated_at)) }}</p></div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Customer summary</CardTitle></CardHeader>
                <CardContent class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><p class="text-muted-foreground">Total orders</p><p class="mt-1 text-2xl font-semibold">{{ summary.total_orders }}</p></div>
                    <div><p class="text-muted-foreground">Completed pautang</p><p class="mt-1 text-2xl font-semibold">{{ summary.completed_pautang }}</p></div>
                    <div><p class="text-muted-foreground">Active pautang</p><p class="mt-1 text-2xl font-semibold">{{ summary.active_pautang }}</p></div>
                    <div><p class="text-muted-foreground">On-time payments</p><p class="mt-1 text-2xl font-semibold">{{ summary.on_time_payments }}</p></div>
                    <div><p class="text-muted-foreground">Late payments</p><p class="mt-1 text-2xl font-semibold">{{ summary.late_payments }}</p></div>
                    <div><p class="text-muted-foreground">Outstanding balance</p><p class="mt-1 text-2xl font-semibold">₱{{ summary.outstanding_balance.toFixed(2) }}</p></div>
                    <div><p class="text-muted-foreground">Current points</p><p class="mt-1 text-2xl font-semibold">{{ summary.current_points }}</p></div>
                </CardContent>
            </Card>
        </div>

        <ConfirmModal
            :open="suspendDialogOpen"
            title="Suspend customer"
            :description="`Suspend ${customer.name}? They will no longer be able to access the application.`"
            confirm-label="Suspend customer"
            :processing="suspending"
            @confirm="suspendCustomer"
            @close="suspendDialogOpen = false"
        />
    </AppLayout>
</template>
