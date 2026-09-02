<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { movementTypeLabels, type MovementType, type RiceProduct } from '@/modules/inventory/types';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ product: RiceProduct }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rice inventory', href: route('rice-products.index') },
    { title: props.product.name, href: route('rice-products.show', { riceProduct: props.product.id }) },
    { title: 'Adjust stock', href: route('rice-products.stock.create', { riceProduct: props.product.id }) },
];

const form = useForm({
    type: 'stock_in' as MovementType,
    quantity: 1,
    adjustment_direction: 'increase',
    order_id: '',
    notes: '',
});

const isAdjustment = computed(() => form.type === 'adjustment');
const reducesStock = computed(
    () => form.type === 'order' || form.type === 'damaged' || (form.type === 'adjustment' && form.adjustment_direction === 'decrease'),
);
const submit = () => form.post(route('rice-products.stock.store', { riceProduct: props.product.id }));
</script>

<template>
    <Head title="Adjust stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Adjust stock</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ product.name }} currently has {{ product.available_stock }} available sacks.</p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <FormField id="type" label="Movement type" :error="form.errors.type" required>
                                <select id="type" v-model="form.type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                    <option v-for="(label, type) in movementTypeLabels" :key="type" :value="type">{{ label }}</option>
                                </select>
                            </FormField>

                            <FormField id="quantity" label="Quantity" :error="form.errors.quantity" required>
                                <Input id="quantity" v-model.number="form.quantity" type="number" min="1" step="1" required />
                            </FormField>
                        </div>

                        <FormField
                            v-if="isAdjustment"
                            id="adjustment-direction"
                            label="Adjustment direction"
                            :error="form.errors.adjustment_direction"
                            required
                        >
                            <select
                                id="adjustment-direction"
                                v-model="form.adjustment_direction"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="increase">Increase available stock</option>
                                <option value="decrease">Decrease available stock</option>
                            </select>
                        </FormField>

                        <p class="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                            This movement will {{ reducesStock ? 'reduce' : 'increase' }} available stock.
                        </p>

                        <FormField id="order-id" label="Related order ID" :error="form.errors.order_id">
                            <Input id="order-id" v-model="form.order_id" type="number" min="1" step="1" placeholder="Optional" />
                        </FormField>

                        <FormField id="notes" label="Notes" :error="form.errors.notes">
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder="Optional"
                            />
                        </FormField>

                        <div class="flex justify-end"><Button type="submit" :disabled="form.processing">Record movement</Button></div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
