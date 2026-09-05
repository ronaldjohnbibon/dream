<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  gcashPaymentStatusLabels,
  type GcashPaymentStatus,
  type PaginatedGcashPayments,
} from '@/modules/orders/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

const props = defineProps<{
  payments: PaginatedGcashPayments
  status: 'all' | GcashPaymentStatus
}>()
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payments', href: route('gcash-payments.index') }]
const filters = reactive({ status: props.status })
const filtering = ref(false)
const statuses = Object.keys(gcashPaymentStatusLabels) as GcashPaymentStatus[]
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const applyFilters = () =>
  router.get(route('gcash-payments.index'), filters, {
    preserveState: true,
    replace: true,
    onStart: () => (filtering.value = true),
    onFinish: () => (filtering.value = false),
  })
const statusClass = (status: GcashPaymentStatus) =>
  ({
    pending_verification: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
  })[status]
</script>

<template>
  <Head title="Payments" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="app-page">
      <div>
        <h1 class="page-title">GCash payments</h1>
        <p class="page-description">
          Review customer payment submissions and their proof of payment.
        </p>
      </div>
      <form class="surface-toolbar flex flex-wrap gap-2" @submit.prevent="applyFilters">
        <select
          v-model="filters.status"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All statuses</option>
          <option v-for="item in statuses" :key="item" :value="item">
            {{ gcashPaymentStatusLabels[item] }}
          </option></select
        ><Button type="submit" variant="outline" :loading="filtering" loading-text="Applying…"
          >Apply filter</Button
        >
      </form>
      <DataTable>
        <template #head
          ><tr>
            <th class="px-4 py-3 font-medium">Customer</th>
            <th class="px-4 py-3 font-medium">Order</th>
            <th class="px-4 py-3 font-medium">Amount</th>
            <th class="px-4 py-3 font-medium">Reference</th>
            <th class="px-4 py-3 font-medium">Submitted</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Actions</th>
          </tr></template
        >
        <template #body>
          <tr v-if="payments.data.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
              No GCash payments match this filter.
            </td>
          </tr>
          <tr v-for="payment in payments.data" :key="payment.id">
            <td class="px-4 py-3">
              <p class="font-medium">{{ payment.customer.name }}</p>
              <p class="text-xs text-muted-foreground">
                {{ payment.customer.mobile_number ?? payment.customer.email }}
              </p>
            </td>
            <td class="px-4 py-3">
              <p>{{ payment.order.order_number }}</p>
              <p v-if="payment.installment" class="text-xs text-muted-foreground">
                Give {{ payment.installment.installment_number }}
              </p>
            </td>
            <td class="px-4 py-3 font-medium">{{ currency.format(Number(payment.amount)) }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ payment.reference_number }}</td>
            <td class="px-4 py-3">{{ payment.payment_date }}</td>
            <td class="px-4 py-3">
              <span class="status-pill" :class="statusClass(payment.status)">{{
                gcashPaymentStatusLabels[payment.status]
              }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <Button size="sm" variant="ghost" as-child
                ><Link :href="route('gcash-payments.show', { gcashPayment: payment.id })"
                  >Review</Link
                ></Button
              >
            </td>
          </tr>
        </template>
      </DataTable>
      <div class="flex justify-end"><PaginationLinks :links="payments.links" /></div>
    </div>
  </AppLayout>
</template>
