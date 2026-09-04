<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  movementTypeLabels,
  type MovementFilters,
  type PaginatedMovements,
  type StockMovementWithProduct,
} from '@/modules/inventory/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{
  movements: PaginatedMovements
  filters: MovementFilters
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Rice inventory', href: route('rice-products.index') },
  { title: 'Movement history', href: route('inventory-movements.index') },
]
const filters = ref<MovementFilters>({ ...props.filters })
const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})

const applyFilters = () => {
  router.get(route('inventory-movements.index'), filters.value, {
    preserveState: true,
    replace: true,
  })
}

const movementClass = (movement: StockMovementWithProduct) =>
  movement.new_stock >= movement.previous_stock ? 'text-green-700' : 'text-destructive'
</script>

<template>
  <Head title="Movement history" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Movement history</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Track every recorded change to available stock.
          </p>
        </div>
        <Button variant="outline" as-child
          ><Link :href="route('rice-products.index')">Rice inventory</Link></Button
        >
      </div>

      <form
        class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_180px_auto]"
        @submit.prevent="applyFilters"
      >
        <Input
          v-model="filters.search"
          placeholder="Search rice product or brand"
          aria-label="Search movement history"
        />
        <select
          v-model="filters.type"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All movement types</option>
          <option v-for="(label, type) in movementTypeLabels" :key="type" :value="type">
            {{ label }}
          </option>
        </select>
        <Button type="submit" variant="outline">Apply filters</Button>
      </form>

      <DataTable>
        <template #head
          ><tr>
            <th class="px-4 py-3 font-medium">Date</th>
            <th class="px-4 py-3 font-medium">Rice product</th>
            <th class="px-4 py-3 font-medium">Type</th>
            <th class="px-4 py-3 font-medium">Quantity</th>
            <th class="px-4 py-3 font-medium">Stock</th>
            <th class="px-4 py-3 font-medium">Order</th>
            <th class="px-4 py-3 font-medium">Notes</th>
            <th class="px-4 py-3 font-medium">User</th>
          </tr></template
        >
        <template #body>
          <tr v-if="movements.data.length === 0">
            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
              No stock movements match the current filters.
            </td>
          </tr>
          <tr v-for="movement in movements.data" :key="movement.id">
            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
              {{ dateFormatter.format(new Date(movement.created_at)) }}
            </td>
            <td class="px-4 py-3">
              <Link
                class="font-medium hover:underline"
                :href="route('rice-products.show', { riceProduct: movement.rice_product.id })"
                >{{ movement.rice_product.name }}</Link
              >
              <p class="text-xs text-muted-foreground">{{ movement.rice_product.brand }}</p>
            </td>
            <td class="px-4 py-3">{{ movementTypeLabels[movement.type] }}</td>
            <td class="px-4 py-3 font-medium" :class="movementClass(movement)">
              {{ movement.new_stock >= movement.previous_stock ? '+' : '-' }}{{ movement.quantity }}
            </td>
            <td class="px-4 py-3">{{ movement.previous_stock }} → {{ movement.new_stock }}</td>
            <td class="px-4 py-3">{{ movement.order_id ?? '—' }}</td>
            <td
              class="max-w-56 truncate px-4 py-3 text-muted-foreground"
              :title="movement.notes ?? undefined"
            >
              {{ movement.notes ?? '—' }}
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ movement.user_name }}</td>
          </tr>
        </template>
      </DataTable>

      <div class="flex justify-end text-sm text-muted-foreground">
        <PaginationLinks :links="movements.links" />
      </div>
    </div>
  </AppLayout>
</template>
