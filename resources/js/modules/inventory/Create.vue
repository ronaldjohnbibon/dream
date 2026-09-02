<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import RiceProductForm from '@/modules/inventory/components/RiceProductForm.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rice inventory', href: route('rice-products.index') },
    { title: 'Add rice', href: route('rice-products.create') },
];

const form = useForm({
    name: '',
    brand: '',
    description: '',
    cost_price: '',
    selling_price: '',
    initial_stock: 0,
    reorder_level: 0,
});

const submit = () => form.post(route('rice-products.store'));
</script>

<template>
    <Head title="Add rice" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader><CardTitle>Add rice product</CardTitle></CardHeader>
                <CardContent><RiceProductForm :form="form" submit-label="Create rice product" @submit="submit" /></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
