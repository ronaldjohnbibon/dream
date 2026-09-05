<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import type {
  Customer,
  CustomerFilters,
  CustomerStatus,
  PaginatedCustomers,
} from '@/modules/customers/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{
  customers: PaginatedCustomers
  filters: CustomerFilters
}>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Customers', href: route('customers.index') }]
const filters = ref<CustomerFilters>({ ...props.filters })
const filtering = ref(false)
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' })

const applyFilters = () => {
  router.get(route('customers.index'), filters.value, {
    preserveState: true,
    replace: true,
    onStart: () => (filtering.value = true),
    onFinish: () => (filtering.value = false),
  })
}

const sortBy = (sort: CustomerFilters['sort']) => {
  filters.value.sort = sort
  filters.value.direction =
    props.filters.sort === sort && props.filters.direction === 'asc' ? 'desc' : 'asc'
  applyFilters()
}

const sortIndicator = (sort: CustomerFilters['sort']) =>
  props.filters.sort === sort ? (props.filters.direction === 'asc' ? ' ↑' : ' ↓') : ''

const statusLabel = (status: CustomerStatus) =>
  ({
    good_standing: 'Good Standing',
    overdue: 'Overdue',
    suspended: 'Suspended',
  })[status]

const statusClass = (status: CustomerStatus) =>
  ({
    good_standing: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    overdue: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
  })[status]
</script>

<template>
  <Head title="Customers" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="app-page">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <h1 class="page-title">Customers</h1>
          <p class="page-description">Manage customer profiles and account status.</p>
        </div>
        <Button as-child><Link :href="route('customers.create')">Create customer</Link></Button>
      </div>

      <form
        class="surface-toolbar grid gap-3 md:grid-cols-[1fr_180px_auto]"
        @submit.prevent="applyFilters"
      >
        <Input
          v-model="filters.search"
          placeholder="Search name, email, or mobile number"
          aria-label="Search customers"
        />
        <select
          v-model="filters.status"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All statuses</option>
          <option value="good_standing">Good Standing</option>
          <option value="overdue">Overdue</option>
          <option value="suspended">Suspended</option>
        </select>
        <Button type="submit" variant="outline" :loading="filtering" loading-text="Applying…"
          >Apply filters</Button
        >
      </form>

      <DataTable>
        <template #head>
          <tr>
            <th class="px-4 py-3 font-medium">
              <button type="button" @click="sortBy('name')">Name{{ sortIndicator('name') }}</button>
            </th>
            <th class="px-4 py-3 font-medium">
              <button type="button" @click="sortBy('email')">
                Email{{ sortIndicator('email') }}
              </button>
            </th>
            <th class="px-4 py-3 font-medium">
              <button type="button" @click="sortBy('mobile_number')">
                Mobile{{ sortIndicator('mobile_number') }}
              </button>
            </th>
            <th class="px-4 py-3 font-medium">Delivery area</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">
              <button type="button" @click="sortBy('created_at')">
                Created{{ sortIndicator('created_at') }}
              </button>
            </th>
            <th class="px-4 py-3 text-right font-medium">Actions</th>
          </tr>
        </template>
        <template #body>
          <tr v-if="customers.data.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
              No customers match the current filters.
            </td>
          </tr>
          <tr v-for="customer in customers.data" :key="customer.id">
            <td class="px-4 py-3 font-medium">{{ customer.name }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ customer.email || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ customer.mobile_number }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ customer.delivery_area }}</td>
            <td class="px-4 py-3">
              <span class="status-pill" :class="statusClass(customer.account_status)">{{
                statusLabel(customer.account_status)
              }}</span>
            </td>
            <td class="px-4 py-3 text-muted-foreground">
              {{ dateFormatter.format(new Date(customer.created_at)) }}
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <Button variant="ghost" size="sm" as-child
                  ><Link :href="route('customers.show', { customer: customer.id })"
                    >View</Link
                  ></Button
                >
                <Button variant="ghost" size="sm" as-child
                  ><Link :href="route('customers.edit', { customer: customer.id })"
                    >Edit</Link
                  ></Button
                >
              </div>
            </td>
          </tr>
        </template>
      </DataTable>

      <div class="flex justify-end text-sm text-muted-foreground">
        <PaginationLinks :links="customers.links" />
      </div>
    </div>
  </AppLayout>
</template>
