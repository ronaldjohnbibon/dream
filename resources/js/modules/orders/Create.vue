<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AvailableRiceProduct, DeliveryArea } from '@/modules/orders/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    products: AvailableRiceProduct[];
    areas: DeliveryArea[];
    customer: { complete_address: string | null; delivery_area: string | null };
    points: { enabled: boolean; balance: number; peso_per_point: string; minimum_redemption: number; maximum_points_usable: number };
    pautang: { enabled: boolean; maximum_sacks: number; has_unpaid_order: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My orders', href: route('orders.index') },
    { title: 'Place order', href: route('orders.create') },
];
const form = useForm({
    rice_product_id: null as number | null,
    quantity: 1,
    payment_type: 'cash' as 'cash' | 'pautang',
    points_to_use: 0,
    delivery_address: props.customer.complete_address ?? '',
    delivery_area_id: props.areas.find((area) => area.name === props.customer.delivery_area)?.id ?? null as number | null,
    notes: '',
});
const selectedProduct = computed(() => props.products.find((product) => product.id === form.rice_product_id) ?? null);
const selectedArea = computed(() => props.areas.find((area) => area.id === form.delivery_area_id) ?? null);
const subtotal = computed(() => (selectedProduct.value ? Number((Number(selectedProduct.value.selling_price) * form.quantity).toFixed(2)) : 0));
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const pesoPerPoint = computed(() => Number(props.points.peso_per_point));
const pesoEquivalent = computed(() => props.points.balance * pesoPerPoint.value);
const maximumPointsToUse = computed(() => {
    if (! props.points.enabled || form.payment_type !== 'cash' || pesoPerPoint.value <= 0 || subtotal.value <= 0) {
        return 0;
    }

    return Math.min(props.points.balance, props.points.maximum_points_usable, Math.floor((subtotal.value + Number.EPSILON) / pesoPerPoint.value));
});
const pointsToUse = computed(() => Math.max(0, Math.min(Math.floor(Number(form.points_to_use) || 0), maximumPointsToUse.value)));
const pointsDiscount = computed(() => Number((pointsToUse.value * pesoPerPoint.value).toFixed(2)));
const deliveryFee = computed(() => Number(selectedArea.value?.delivery_fee ?? 0));
const finalAmount = computed(() => Math.max(0, Number((subtotal.value - pointsDiscount.value + deliveryFee.value).toFixed(2))));
const finalAmountWithMaximumPoints = computed(() => Math.max(0, Number((subtotal.value - (maximumPointsToUse.value * pesoPerPoint.value)).toFixed(2))));
const maximumQuantity = computed(() => Math.min(selectedProduct.value?.available_stock ?? 0, form.payment_type === 'pautang' ? props.pautang.maximum_sacks : Number.MAX_SAFE_INTEGER));
const canPayFullyWithPoints = computed(() => selectedProduct.value !== null
    && form.quantity === 1
    && maximumPointsToUse.value > 0
    && finalAmountWithMaximumPoints.value === 0);

watch([() => form.payment_type, maximumPointsToUse], () => {
    if (form.payment_type === 'pautang') {
        form.points_to_use = 0;
        if (form.quantity > props.pautang.maximum_sacks) form.quantity = props.pautang.maximum_sacks;
    } else if (form.points_to_use > maximumPointsToUse.value) {
        form.points_to_use = maximumPointsToUse.value;
    }
});

const payFullyUsingPoints = () => {
    if (canPayFullyWithPoints.value) {
        form.points_to_use = maximumPointsToUse.value;
    }
};

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
                            <Input id="quantity" v-model.number="form.quantity" type="number" min="1" :max="maximumQuantity" step="1" required />
                        </FormField>
                        <div v-if="selectedProduct" class="rounded-md border bg-muted/30 p-4 text-sm sm:col-span-2">
                            <div class="flex flex-wrap justify-between gap-2"><span>Unit price</span><span class="font-medium">{{ currency.format(Number(selectedProduct.selling_price)) }}</span></div>
                            <div class="mt-2 flex flex-wrap justify-between gap-2 text-base"><span class="font-medium">Subtotal</span><span class="font-semibold">{{ currency.format(subtotal) }}</span></div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Redeem points</CardTitle>
                        <CardDescription>Points can be used on Cash orders and are converted using the current rate.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-md border bg-muted/30 p-4"><p class="text-sm text-muted-foreground">Available points</p><p class="mt-1 text-xl font-semibold">{{ points.balance }}</p></div>
                            <div class="rounded-md border bg-muted/30 p-4"><p class="text-sm text-muted-foreground">Peso equivalent</p><p class="mt-1 text-xl font-semibold">{{ currency.format(pesoEquivalent) }}</p></div>
                            <div class="rounded-md border bg-muted/30 p-4"><p class="text-sm text-muted-foreground">Discount</p><p class="mt-1 text-xl font-semibold">{{ currency.format(pointsDiscount) }}</p></div>
                            <div class="rounded-md border bg-muted/30 p-4"><p class="text-sm text-muted-foreground">Final amount</p><p class="mt-1 text-xl font-semibold">{{ currency.format(finalAmount) }}</p></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[minmax(0,240px)_1fr] sm:items-end">
                            <FormField id="points-to-use" label="Points to use" :error="form.errors.points_to_use">
                                <Input id="points-to-use" v-model.number="form.points_to_use" type="number" min="0" :max="maximumPointsToUse" step="1" :disabled="form.payment_type === 'pautang' || maximumPointsToUse === 0" />
                            </FormField>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span>Use at least {{ points.minimum_redemption }} and up to {{ maximumPointsToUse }} points on this order.</span>
                                <Button v-if="canPayFullyWithPoints" type="button" size="sm" variant="outline" @click="payFullyUsingPoints">Pay Fully Using Points</Button>
                            </div>
                        </div>
                        <p v-if="form.payment_type === 'pautang'" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Points redemption is available for Cash orders only.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Payment and delivery</CardTitle></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <FormField id="payment-type" label="Payment type" :error="form.errors.payment_type" required>
                            <select id="payment-type" v-model="form.payment_type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                                <option value="cash">Cash</option>
                                <option v-if="pautang.enabled && !pautang.has_unpaid_order" value="pautang">Pautang</option>
                            </select>
                        </FormField>
                        <p v-if="pautang.enabled && pautang.has_unpaid_order" class="self-end rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 sm:col-span-2">
                            You have an unpaid pautang balance. Complete it before creating another pautang order; cash orders are still available.
                        </p>
                        <p v-if="form.payment_type === 'pautang'" class="self-end rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Pautang is limited to {{ pautang.maximum_sacks }} sack(s). You must complete it before creating another pautang order.
                        </p>
                        <FormField id="delivery-area" label="Delivery area" :error="form.errors.delivery_area_id" required>
                            <select id="delivery-area" v-model.number="form.delivery_area_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" required>
                                <option :value="null" disabled>Select delivery area</option>
                                <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }} · {{ currency.format(Number(area.delivery_fee)) }}</option>
                            </select>
                        </FormField>
                        <FormField id="delivery-address" label="Delivery address" :error="form.errors.delivery_address" required>
                            <textarea id="delivery-address" v-model="form.delivery_address" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" required />
                        </FormField>
                        <FormField id="notes" label="Notes" :error="form.errors.notes">
                            <textarea id="notes" v-model="form.notes" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Optional delivery notes" />
                        </FormField>
                        <div class="rounded-md border bg-muted/30 p-4 text-sm sm:col-span-2"><div class="flex justify-between gap-3"><span>Delivery fee</span><span class="font-medium">{{ currency.format(deliveryFee) }}</span></div><div class="mt-2 flex justify-between gap-3 text-base"><span class="font-medium">Order total</span><span class="font-semibold">{{ currency.format(finalAmount) }}</span></div></div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="route('orders.index')">Cancel</Link></Button><Button type="submit" :disabled="form.processing">Place order</Button></div>
            </form>
        </div>
    </AppLayout>
</template>
