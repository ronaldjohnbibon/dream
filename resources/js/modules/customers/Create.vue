<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import CustomerForm from '@/modules/customers/components/CustomerForm.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: route('customers.index') },
    { title: 'Create customer', href: route('customers.create') },
];

const form = useForm({
    name: '',
    email: '',
    mobile_number: '',
    complete_address: '',
    delivery_area: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('customers.store'));
</script>

<template>
    <Head title="Create customer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader><CardTitle>Create customer</CardTitle></CardHeader>
                <CardContent><CustomerForm :form="form" submit-label="Create customer" @submit="submit" /></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
