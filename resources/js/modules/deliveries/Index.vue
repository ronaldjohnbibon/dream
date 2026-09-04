<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import { deliveryStatusLabels, type DeliveryStatus } from '@/modules/orders/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
type QueueDelivery = {
  id: number
  order: { id: number; order_number: string; quantity: number; final_amount: string }
  customer: { name: string; mobile_number: string | null }
  delivery_area_name: string
  delivery_date: string | null
  delivery_person: string | null
  status: DeliveryStatus
}
const props = defineProps<{
  deliveries: { data: QueueDelivery[]; links: any[] }
  filters: { status: 'all' | DeliveryStatus; delivery_date: string }
}>()
const filters = ref({ ...props.filters })
const statuses = Object.keys(deliveryStatusLabels) as DeliveryStatus[]
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Deliveries', href: route('deliveries.index') }]
</script>
<template>
  <Head title="Deliveries" /><AppLayout :breadcrumbs="breadcrumbs"
    ><div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Deliveries</h1>
        <p class="mt-1 text-sm text-muted-foreground">Schedule and track customer deliveries.</p>
      </div>
      <form
        class="flex flex-wrap gap-3 rounded-lg border bg-card p-4"
        @submit.prevent="
          router.get(route('deliveries.index'), filters, { preserveState: true, replace: true })
        "
      >
        <select
          v-model="filters.status"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All statuses</option>
          <option v-for="status in statuses" :key="status" :value="status">
            {{ deliveryStatusLabels[status] }}
          </option></select
        ><Input v-model="filters.delivery_date" type="date" class="max-w-48" /><Button
          type="submit"
          variant="outline"
          >Apply filters</Button
        >
      </form>
      <DataTable
        ><template #head
          ><tr>
            <th class="px-4 py-3 font-medium">Order</th>
            <th class="px-4 py-3 font-medium">Customer</th>
            <th class="px-4 py-3 font-medium">Area</th>
            <th class="px-4 py-3 font-medium">Schedule</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Action</th>
          </tr></template
        ><template #body
          ><tr v-if="deliveries.data.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
              No deliveries match the current filters.
            </td>
          </tr>
          <tr v-for="delivery in deliveries.data" :key="delivery.id">
            <td class="px-4 py-3 font-medium">{{ delivery.order.order_number }}</td>
            <td class="px-4 py-3">
              <p>{{ delivery.customer.name }}</p>
              <p class="text-xs text-muted-foreground">{{ delivery.customer.mobile_number }}</p>
            </td>
            <td class="px-4 py-3">{{ delivery.delivery_area_name }}</td>
            <td class="px-4 py-3">{{ delivery.delivery_date ?? 'Not scheduled' }}</td>
            <td class="px-4 py-3">{{ deliveryStatusLabels[delivery.status] }}</td>
            <td class="px-4 py-3 text-right">
              <Button size="sm" variant="ghost" as-child
                ><Link :href="route('deliveries.show', { delivery: delivery.id })"
                  >Manage</Link
                ></Button
              >
            </td>
          </tr></template
        ></DataTable
      >
      <div class="flex justify-end"><PaginationLinks :links="deliveries.links" /></div></div
  ></AppLayout>
</template>
