<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { movementTypeLabels, type RiceProduct, type StockMovement } from '@/modules/inventory/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    product: RiceProduct;
    recentMovements: StockMovement[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rice inventory', href: route('rice-products.index') },
    { title: props.product.name, href: route('rice-products.show', { riceProduct: props.product.id }) },
];
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

const toggleStatus = () => {
    router.patch(route('rice-products.status', { riceProduct: props.product.id }), {}, { preserveScroll: true });
};

const movementClass = (movement: StockMovement) => (movement.new_stock >= movement.previous_stock ? 'text-green-700' : 'text-destructive');
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
                        <span
                            class="rounded-full px-2 py-1 text-xs"
                            :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground'"
                            >{{ product.is_active ? 'Active' : 'Inactive' }}</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">{{ product.brand }} · 25kg sack</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="toggleStatus">{{ product.is_active ? 'Deactivate' : 'Activate' }}</Button>
                    <Button variant="outline" as-child><Link :href="route('rice-products.edit', { riceProduct: product.id })">Edit</Link></Button>
                    <Button as-child><Link :href="route('rice-products.stock.create', { riceProduct: product.id })">Adjust stock</Link></Button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card
                    ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Available stock</CardTitle></CardHeader
                    ><CardContent
                        ><p class="text-3xl font-semibold">{{ product.available_stock }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Reserved stock</CardTitle></CardHeader
                    ><CardContent
                        ><p class="text-3xl font-semibold">{{ product.reserved_stock }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader class="pb-2"><CardTitle class="text-sm font-medium text-muted-foreground">Reorder level</CardTitle></CardHeader
                    ><CardContent
                        ><p class="text-3xl font-semibold">{{ product.reorder_level }}</p></CardContent
                    ></Card
                >
            </div>

            <Card>
                <CardHeader><CardTitle>Product details</CardTitle></CardHeader>
                <CardContent class="grid gap-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-muted-foreground">Brand</p>
                        <p class="mt-1 font-medium">{{ product.brand }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Sack size</p>
                        <p class="mt-1 font-medium">25kg</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Cost price</p>
                        <p class="mt-1 font-medium">{{ currency.format(Number(product.cost_price)) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">Selling price</p>
                        <p class="mt-1 font-medium">{{ currency.format(Number(product.selling_price)) }}</p>
                    </div>
                    <div v-if="product.description" class="sm:col-span-2">
                        <p class="text-muted-foreground">Description</p>
                        <p class="mt-1 whitespace-pre-line font-medium">{{ product.description }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Recent stock movements</h2>
                    <p class="text-sm text-muted-foreground">The latest changes to available stock.</p>
                </div>
                <Button variant="outline" as-child><Link :href="route('inventory-movements.index')">View all history</Link></Button>
            </div>

            <DataTable>
                <template #head
                    ><tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Quantity</th>
                        <th class="px-4 py-3 font-medium">Stock</th>
                        <th class="px-4 py-3 font-medium">Notes</th>
                        <th class="px-4 py-3 font-medium">User</th>
                    </tr></template
                >
                <template #body>
                    <tr v-if="recentMovements.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No stock movements yet.</td>
                    </tr>
                    <tr v-for="movement in recentMovements" :key="movement.id">
                        <td class="px-4 py-3 text-muted-foreground">{{ dateFormatter.format(new Date(movement.created_at)) }}</td>
                        <td class="px-4 py-3">{{ movementTypeLabels[movement.type] }}</td>
                        <td class="px-4 py-3 font-medium" :class="movementClass(movement)">
                            {{ movement.new_stock >= movement.previous_stock ? '+' : '-' }}{{ movement.quantity }}
                        </td>
                        <td class="px-4 py-3">{{ movement.previous_stock }} → {{ movement.new_stock }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ movement.notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ movement.user_name }}</td>
                    </tr>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
