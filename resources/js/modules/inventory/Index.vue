<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import PaginationLinks from '@/components/shared/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedProducts, ProductFilters, RiceProduct } from '@/modules/inventory/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    products: PaginatedProducts;
    filters: ProductFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rice inventory', href: route('rice-products.index') }];
const filters = ref<ProductFilters>({ ...props.filters });
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

const applyFilters = () => {
    router.get(
        route('rice-products.index'),
        {
            ...filters.value,
            low_stock: filters.value.low_stock ? '1' : '0',
        },
        { preserveState: true, replace: true },
    );
};

const toggleStatus = (product: RiceProduct) => {
    router.patch(route('rice-products.status', { riceProduct: product.id }), {}, { preserveScroll: true });
};

const isLowStock = (product: RiceProduct) => product.is_active && product.available_stock <= product.reorder_level;
</script>

<template>
    <Head title="Rice inventory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Rice inventory</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage rice products and available stock.</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link :href="route('inventory-movements.index')">Movement history</Link></Button>
                    <Button as-child><Link :href="route('rice-products.create')">Add rice</Link></Button>
                </div>
            </div>

            <form class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_180px_auto_auto]" @submit.prevent="applyFilters">
                <Input v-model="filters.search" placeholder="Search name or brand" aria-label="Search rice products" />
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <label class="flex h-9 items-center gap-2 rounded-md border border-input px-3 text-sm">
                    <input v-model="filters.low_stock" type="checkbox" class="size-4 rounded border-input" />
                    Low stock
                </label>
                <Button type="submit" variant="outline">Apply filters</Button>
            </form>

            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 font-medium">Rice product</th>
                        <th class="px-4 py-3 font-medium">Prices</th>
                        <th class="px-4 py-3 font-medium">Available</th>
                        <th class="px-4 py-3 font-medium">Reserved</th>
                        <th class="px-4 py-3 font-medium">Reorder level</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </template>
                <template #body>
                    <tr v-if="products.data.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No rice products match the current filters.</td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ product.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ product.brand }} · 25kg sack</p>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <p>Cost: {{ currency.format(Number(product.cost_price)) }}</p>
                            <p>Sell: {{ currency.format(Number(product.selling_price)) }}</p>
                        </td>
                        <td class="px-4 py-3 font-medium" :class="isLowStock(product) && 'text-destructive'">{{ product.available_stock }}</td>
                        <td class="px-4 py-3">{{ product.reserved_stock }}</td>
                        <td class="px-4 py-3">{{ product.reorder_level }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs"
                                :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground'"
                            >
                                {{ product.is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="isLowStock(product)" class="ml-2 rounded-full bg-red-100 px-2 py-1 text-xs text-red-800">Low stock</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button size="sm" variant="ghost" as-child
                                    ><Link :href="route('rice-products.show', { riceProduct: product.id })">View</Link></Button
                                >
                                <Button size="sm" variant="ghost" as-child
                                    ><Link :href="route('rice-products.edit', { riceProduct: product.id })">Edit</Link></Button
                                >
                                <Button size="sm" variant="ghost" @click="toggleStatus(product)">{{
                                    product.is_active ? 'Deactivate' : 'Activate'
                                }}</Button>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>

            <div class="flex justify-end text-sm text-muted-foreground"><PaginationLinks :links="products.links" /></div>
        </div>
    </AppLayout>
</template>
