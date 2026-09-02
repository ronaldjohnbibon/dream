<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AvailableRiceProduct } from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    products: AvailableRiceProduct[];
    customer: { complete_address: string | null; delivery_area: string | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My orders', href: route('orders.index') },
    { title: 'Place order', href: route('orders.create') },
];
const form = useForm({
    rice_product_id: null as number | null,
    quantity: 1,
    payment_type: 'cash' as 'cash' | 'pautang',
    delivery_address: props.customer.complete_address ?? '',
    delivery_area: props.customer.delivery_area ?? '',
    notes: '',
});
const selectedProduct = computed(() => props.products.find((product) => product.id === form.rice_product_id) ?? null);
const subtotal = computed(() => (selectedProduct.value ? Number(selectedProduct.value.selling_price) * form.quantity : 0));
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

const submit = () => {
    form.post(route('orders.store'));
};
</script>

<template>
    <Head title="Place order" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Place an order</h1>
                <p class="mt-1 text-sm text-muted-foreground">Choose rice, delivery details, and your payment type.</p>
            </div>

            <Card v-if="products.length === 0">
                <CardHeader>
                    <CardTitle>No rice available</CardTitle>
                    <CardDescription>There are no active rice products in stock right now.</CardDescription>
                </CardHeader>
                <CardContent><Button variant="outline" as-child><Link :href="route('orders.index')">Back to my orders</Link></Button></CardContent>
            </Card>

            <form v-else class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader><CardTitle>Rice order</CardTitle></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <FormField id="rice-product" label="Rice product" :error="form.errors.rice_product_id" required>
                            <select id="rice-product" v-model.number="form.rice_product_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" required>
                                <option :value="null" disabled>Select rice</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} · {{ product.brand }} ({{ product.available_stock }} sacks)
                                </option>
                            </select>
                        </FormField>
                        <FormField id="quantity" label="Quantity (sacks)" :error="form.errors.quantity" required>
                            <Input id="quantity" v-model.number="form.quantity" type="number" min="1" :max="selectedProduct?.available_stock" step="1" required />
                        </FormField>
                        <div v-if="selectedProduct" class="rounded-md border bg-muted/30 p-4 text-sm sm:col-span-2">
                            <div class="flex flex-wrap justify-between gap-2"><span>Unit price</span><span class="font-medium">{{ currency.format(Number(selectedProduct.selling_price)) }}</span></div>
                            <div class="mt-2 flex flex-wrap justify-between gap-2 text-base"><span class="font-medium">Subtotal</span><span class="font-semibold">{{ currency.format(subtotal) }}</span></div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Payment and delivery</CardTitle></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <FormField id="payment-type" label="Payment type" :error="form.errors.payment_type" required>
                            <select id="payment-type" v-model="form.payment_type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                <option value="cash">Cash</option>
                                <option value="pautang">Pautang</option>
                            </select>
                        </FormField>
                        <p v-if="form.payment_type === 'pautang'" class="self-end rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Pautang is limited to one sack and requires no unpaid Pautang order.
                        </p>
                        <FormField id="delivery-area" label="Delivery area" :error="form.errors.delivery_area" required>
                            <Input id="delivery-area" v-model="form.delivery_area" required />
                        </FormField>
                        <FormField id="delivery-address" label="Delivery address" :error="form.errors.delivery_address" required>
                            <textarea id="delivery-address" v-model="form.delivery_address" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" required />
                        </FormField>
                        <FormField id="notes" label="Notes" :error="form.errors.notes">
                            <textarea id="notes" v-model="form.notes" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Optional delivery notes" />
                        </FormField>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="route('orders.index')">Cancel</Link></Button><Button type="submit" :disabled="form.processing">Place order</Button></div>
            </form>
        </div>
    </AppLayout>
</template>
