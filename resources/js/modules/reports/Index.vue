<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import { paymentStatusLabels } from '@/modules/orders/types'
import type {
  AgingBucket,
  BalanceReportRow,
  InventoryReportRow,
  MovementReportRow,
  OverdueCustomerRow,
  PautangReportRow,
  PaymentReportRow,
  PointReportRow,
  ProfitReportRow,
  ReportFilters,
  ReportOrderRow,
  ReportPaginator,
  ReportSummaries,
} from '@/modules/reports/types'
import type { BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  filters: ReportFilters
  customers: { id: number; name: string }[]
  summaries: ReportSummaries
  aging: Record<string, AgingBucket>
  orders: ReportPaginator<ReportOrderRow>
  pautang: ReportPaginator<PautangReportRow>
  outstanding: ReportPaginator<BalanceReportRow>
  overdueCustomers: ReportPaginator<OverdueCustomerRow>
  payments: ReportPaginator<PaymentReportRow>
  inventory: ReportPaginator<InventoryReportRow>
  movements: ReportPaginator<MovementReportRow>
  pointsEarned: ReportPaginator<PointReportRow>
  pointsRedeemed: ReportPaginator<PointReportRow>
  profitByProduct: ReportPaginator<ProfitReportRow>
}>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: route('reports.index') }]
const filters = ref({
  ...props.filters,
  customer_id: props.filters.customer_id ? String(props.filters.customer_id) : '',
})
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const orderStatusLabels: Record<string, string> = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  preparing: 'Preparing',
  out_for_delivery: 'Out for Delivery',
  delivered: 'Delivered',
  completed: 'Completed',
  cancelled: 'Cancelled',
}
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' })
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})
const formatCurrency = (amount: string | number) => currency.format(Number(amount))
const formatDate = (date: string) => dateFormatter.format(new Date(`${date}T00:00:00`))
const formatDateTime = (date: string) => dateTimeFormatter.format(new Date(date))
const agingTotal = computed(() =>
  Object.values(props.aging).reduce((total, bucket) => total + Number(bucket.amount), 0)
)
const applyFilters = () =>
  router.get(route('reports.index'), filters.value, { preserveState: true, replace: true })
const resetFilters = () => router.get(route('reports.index'))
const label = (value: string | null, labels: Record<string, string>) =>
  value ? (labels[value] ?? value.replaceAll('_', ' ')) : '—'
</script>

<template>
  <Head title="Reports" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="app-page">
      <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-description">
          Track sales, receivables, inventory, points, and estimated profit.
        </p>
      </div>

      <form
        class="surface-toolbar grid gap-3 md:grid-cols-2 xl:grid-cols-6"
        @submit.prevent="applyFilters"
      >
        <label class="grid gap-1 text-sm font-medium"
          >From<input
            v-model="filters.date_from"
            type="date"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        /></label>
        <label class="grid gap-1 text-sm font-medium"
          >To<input
            v-model="filters.date_to"
            type="date"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        /></label>
        <label class="grid gap-1 text-sm font-medium"
          >Customer<select
            v-model="filters.customer_id"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="">All customers</option>
            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
              {{ customer.name }}
            </option>
          </select></label
        >
        <label class="grid gap-1 text-sm font-medium"
          >Payment status<select
            v-model="filters.payment_status"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="all">All statuses</option>
            <option
              v-for="(statusLabel, status) in paymentStatusLabels"
              :key="status"
              :value="status"
            >
              {{ statusLabel }}
            </option>
          </select></label
        >
        <label class="grid gap-1 text-sm font-medium"
          >Order status<select
            v-model="filters.order_status"
            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="all">All statuses</option>
            <option
              v-for="(statusLabel, status) in orderStatusLabels"
              :key="status"
              :value="status"
            >
              {{ statusLabel }}
            </option>
          </select></label
        >
        <div class="flex items-end gap-2 xl:col-span-6">
          <Button type="submit" variant="outline">Apply filters</Button
          ><Button type="button" variant="ghost" @click="resetFilters">Reset</Button>
        </div>
      </form>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Sales and orders</h2>
          <p class="text-sm text-muted-foreground">
            Booked, non-cancelled order value for the selected period.
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Sales</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ formatCurrency(summaries.sales.amount) }}</p>
              <p class="text-sm text-muted-foreground">
                {{ summaries.sales.order_count }} orders
              </p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Average order value</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.sales.average_order_value) }}
              </p>
              <p class="text-sm text-muted-foreground">For non-cancelled orders</p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Orders</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.orders.count }}</p>
              <p class="text-sm text-muted-foreground">
                Matching all selected statuses
              </p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Order status mix</CardTitle></CardHeader
            ><CardContent
              ><p class="text-sm leading-6 text-muted-foreground">
                <span
                  v-for="(count, status) in summaries.orders.statuses"
                  :key="status"
                  class="mr-2 inline-block"
                  >{{ label(status, orderStatusLabels) }}: {{ count }}</span
                >
              </p></CardContent
            ></Card
          >
        </div>
        <Card
          ><CardContent class="pt-6"
            ><DataTable
              ><template #head
                ><tr>
                  <th class="px-4 py-3 font-medium">Order</th>
                  <th class="px-4 py-3 font-medium">Customer</th>
                  <th class="px-4 py-3 font-medium">Product</th>
                  <th class="px-4 py-3 font-medium">Date</th>
                  <th class="px-4 py-3 font-medium">Amount</th>
                  <th class="px-4 py-3 font-medium">Payment status</th>
                  <th class="px-4 py-3 font-medium">Status</th>
                </tr></template
              ><template #body
                ><tr v-if="orders.data.length === 0">
                  <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                    No orders match the current filters.
                  </td>
                </tr>
                <tr v-for="order in orders.data" :key="order.id">
                  <td class="px-4 py-3 font-medium">{{ order.order_number }}</td>
                  <td class="px-4 py-3">{{ order.customer_name }}</td>
                  <td class="px-4 py-3">{{ order.product_name }}</td>
                  <td class="px-4 py-3">{{ formatDate(order.order_date) }}</td>
                  <td class="px-4 py-3 font-medium">{{ formatCurrency(order.final_amount) }}</td>
                  <td class="px-4 py-3">{{ label(order.payment_status, paymentStatusLabels) }}</td>
                  <td class="px-4 py-3">{{ label(order.order_status, orderStatusLabels) }}</td>
                </tr></template
              ></DataTable
            >
            <div class="mt-4"><PaginationLinks :links="orders.links" /></div></CardContent
        ></Card>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Receivables</h2>
          <p class="text-sm text-muted-foreground">
            Open balances and aging are complete as of {{ formatDate(filters.date_to) }}.
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Pautang</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.pautang.remaining_balance) }}
              </p>
              <p class="text-sm text-muted-foreground">
                {{ summaries.pautang.order_count }} active credit orders ·
                {{ formatCurrency(summaries.pautang.amount_paid) }} paid
              </p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Outstanding balances</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.outstanding.amount) }}
              </p>
              <p class="text-sm text-muted-foreground">
                {{ summaries.outstanding.order_count }} open orders
              </p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Overdue customers</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ formatCurrency(summaries.overdue.amount) }}</p>
              <p class="text-sm text-muted-foreground">
                {{ summaries.overdue.customer_count }} customers past due
              </p></CardContent
            ></Card
          >
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Accounts receivable</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ formatCurrency(agingTotal) }}</p>
              <p class="text-sm text-muted-foreground">Pautang aging total</p></CardContent
            ></Card
          >
        </div>
        <div class="grid gap-3 md:grid-cols-5">
          <Card v-for="bucket in aging" :key="bucket.label"
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">{{ bucket.label }}</CardTitle></CardHeader
            ><CardContent
              ><p class="text-xl font-semibold">{{ formatCurrency(bucket.amount) }}</p></CardContent
            ></Card
          >
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
          <Card
            ><CardHeader><CardTitle>Pautang</CardTitle></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Paid</th>
                    <th class="px-4 py-3 font-medium">Balance</th>
                    <th class="px-4 py-3 font-medium">Next due</th>
                  </tr></template
                ><template #body
                  ><tr v-if="pautang.data.length === 0">
                    <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                      No active pautang balances match the filters.
                    </td>
                  </tr>
                  <tr v-for="order in pautang.data" :key="order.id">
                    <td class="px-4 py-3 font-medium">{{ order.order_number }}</td>
                    <td class="px-4 py-3">{{ order.customer_name }}</td>
                    <td class="px-4 py-3">{{ formatCurrency(order.amount_paid) }}</td>
                    <td class="px-4 py-3 font-medium">
                      {{ formatCurrency(order.remaining_balance) }}
                    </td>
                    <td class="px-4 py-3">
                      {{
                        order.next_due_date
                          ? `${formatDate(order.next_due_date)}${order.days_overdue ? ` · ${order.days_overdue}d overdue` : ''}`
                          : 'Not scheduled'
                      }}
                    </td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="pautang.links" /></div></CardContent
          ></Card>
          <Card
            ><CardHeader><CardTitle>Outstanding balances</CardTitle></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Order date</th>
                    <th class="px-4 py-3 font-medium">Balance</th>
                  </tr></template
                ><template #body
                  ><tr v-if="outstanding.data.length === 0">
                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                      No outstanding balances match the filters.
                    </td>
                  </tr>
                  <tr v-for="order in outstanding.data" :key="order.id">
                    <td class="px-4 py-3 font-medium">{{ order.order_number }}</td>
                    <td class="px-4 py-3">{{ order.customer_name }}</td>
                    <td class="px-4 py-3">{{ formatDate(order.order_date) }}</td>
                    <td class="px-4 py-3 font-medium">
                      {{ formatCurrency(order.remaining_balance) }}
                    </td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="outstanding.links" /></div></CardContent
          ></Card>
        </div>
        <Card
          ><CardHeader><CardTitle>Overdue customers</CardTitle></CardHeader
          ><CardContent
            ><DataTable
              ><template #head
                ><tr>
                  <th class="px-4 py-3 font-medium">Customer</th>
                  <th class="px-4 py-3 font-medium">Oldest due date</th>
                  <th class="px-4 py-3 font-medium">Days overdue</th>
                  <th class="px-4 py-3 font-medium">Balance</th>
                </tr></template
              ><template #body
                ><tr v-if="overdueCustomers.data.length === 0">
                  <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                    No overdue customers match the filters.
                  </td>
                </tr>
                <tr v-for="customer in overdueCustomers.data" :key="customer.id">
                  <td class="px-4 py-3 font-medium">{{ customer.name }}</td>
                  <td class="px-4 py-3">{{ formatDate(customer.oldest_due_date) }}</td>
                  <td class="px-4 py-3">{{ customer.days_overdue }}</td>
                  <td class="px-4 py-3 font-medium">
                    {{ formatCurrency(customer.remaining_balance) }}
                  </td>
                </tr></template
              ></DataTable
            >
            <div class="mt-4"><PaginationLinks :links="overdueCustomers.links" /></div></CardContent
        ></Card>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Collections and customer payment history</h2>
          <p class="text-sm text-muted-foreground">Approved GCash receipts by payment date.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Collections</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.collections.amount) }}
              </p>
              <p class="text-sm text-muted-foreground">
                {{ summaries.collections.payment_count }} approved payments
              </p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium"
                >Customer payment history</CardTitle
              ></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.collections.payment_count }}</p>
              <p class="text-sm text-muted-foreground">
                Matching approved payment records
              </p></CardContent
            ></Card
          >
        </div>
        <Card
          ><CardContent class="pt-6"
            ><DataTable
              ><template #head
                ><tr>
                  <th class="px-4 py-3 font-medium">Payment date</th>
                  <th class="px-4 py-3 font-medium">Customer</th>
                  <th class="px-4 py-3 font-medium">Order</th>
                  <th class="px-4 py-3 font-medium">Installment</th>
                  <th class="px-4 py-3 font-medium">Amount</th>
                </tr></template
              ><template #body
                ><tr v-if="payments.data.length === 0">
                  <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                    No approved payments match the current filters.
                  </td>
                </tr>
                <tr v-for="payment in payments.data" :key="payment.id">
                  <td class="px-4 py-3">{{ formatDate(payment.payment_date) }}</td>
                  <td class="px-4 py-3">{{ payment.customer_name }}</td>
                  <td class="px-4 py-3 font-medium">{{ payment.order_number }}</td>
                  <td class="px-4 py-3">
                    {{ payment.installment_number ? `Give ${payment.installment_number}` : '—' }}
                  </td>
                  <td class="px-4 py-3 font-medium">{{ formatCurrency(payment.amount) }}</td>
                </tr></template
              ></DataTable
            >
            <div class="mt-4"><PaginationLinks :links="payments.links" /></div></CardContent
        ></Card>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Inventory and movements</h2>
          <p class="text-sm text-muted-foreground">
            Inventory is a current snapshot; movements use the selected date range.
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Available stock</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.inventory.available_stock }}</p>
              <p class="text-sm text-muted-foreground">Sacks available</p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Reserved stock</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.inventory.reserved_stock }}</p>
              <p class="text-sm text-muted-foreground">Sacks reserved for orders</p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Low stock</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.inventory.low_stock_count }}</p>
              <p class="text-sm text-muted-foreground">
                Active products at the system threshold
              </p></CardContent
            ></Card
          >
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
          <Card
            ><CardHeader><CardTitle>Inventory</CardTitle></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Available</th>
                    <th class="px-4 py-3 font-medium">Reserved</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                  </tr></template
                ><template #body
                  ><tr v-if="inventory.data.length === 0">
                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                      No rice products found.
                    </td>
                  </tr>
                  <tr v-for="product in inventory.data" :key="product.id">
                    <td class="px-4 py-3 font-medium">
                      {{ product.name }}
                      <p class="text-xs font-normal text-muted-foreground">{{ product.brand }}</p>
                    </td>
                    <td class="px-4 py-3">{{ product.available_stock }}</td>
                    <td class="px-4 py-3">{{ product.reserved_stock }}</td>
                    <td class="px-4 py-3">{{ product.is_active ? 'Active' : 'Inactive' }}</td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="inventory.links" /></div></CardContent
          ></Card>
          <Card
            ><CardHeader
              ><CardTitle>Inventory movements</CardTitle>
              <p class="text-sm font-normal text-muted-foreground">
                {{ summaries.movements.count }} records · +{{ summaries.movements.stock_in }} in ·
                -{{ summaries.movements.stock_out }} out
              </p></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Quantity</th>
                    <th class="px-4 py-3 font-medium">Stock</th>
                  </tr></template
                ><template #body
                  ><tr v-if="movements.data.length === 0">
                    <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                      No inventory movements match the date range.
                    </td>
                  </tr>
                  <tr v-for="movement in movements.data" :key="movement.id">
                    <td class="px-4 py-3">{{ formatDateTime(movement.created_at) }}</td>
                    <td class="px-4 py-3">
                      {{
                        movement.rice_product
                          ? `${movement.rice_product.name} ${movement.rice_product.brand}`
                          : 'Deleted product'
                      }}
                    </td>
                    <td class="px-4 py-3">{{ movement.type.replaceAll('_', ' ') }}</td>
                    <td class="px-4 py-3">{{ movement.quantity }}</td>
                    <td class="px-4 py-3">
                      {{ movement.previous_stock }} → {{ movement.new_stock }}
                    </td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="movements.links" /></div></CardContent
          ></Card>
        </div>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Points</h2>
          <p class="text-sm text-muted-foreground">
            Points ledger activity in the selected date range.
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Points earned</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.points.earned }}</p>
              <p class="text-sm text-muted-foreground">Positive ledger entries</p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Points redeemed</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">{{ summaries.points.redeemed }}</p>
              <p class="text-sm text-muted-foreground">Order redemption entries</p></CardContent
            ></Card
          >
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
          <Card
            ><CardHeader><CardTitle>Points earned</CardTitle></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Points</th>
                  </tr></template
                ><template #body
                  ><tr v-if="pointsEarned.data.length === 0">
                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                      No earned points match the filters.
                    </td>
                  </tr>
                  <tr v-for="entry in pointsEarned.data" :key="entry.id">
                    <td class="px-4 py-3">{{ formatDate(entry.transaction_date) }}</td>
                    <td class="px-4 py-3">{{ entry.customer_name }}</td>
                    <td class="px-4 py-3">{{ entry.type.replaceAll('_', ' ') }}</td>
                    <td class="px-4 py-3 font-medium">+{{ entry.points }}</td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="pointsEarned.links" /></div></CardContent
          ></Card>
          <Card
            ><CardHeader><CardTitle>Points redeemed</CardTitle></CardHeader
            ><CardContent
              ><DataTable
                ><template #head
                  ><tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Points</th>
                  </tr></template
                ><template #body
                  ><tr v-if="pointsRedeemed.data.length === 0">
                    <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">
                      No redeemed points match the filters.
                    </td>
                  </tr>
                  <tr v-for="entry in pointsRedeemed.data" :key="entry.id">
                    <td class="px-4 py-3">{{ formatDate(entry.transaction_date) }}</td>
                    <td class="px-4 py-3">{{ entry.customer_name }}</td>
                    <td class="px-4 py-3">{{ entry.order_number ?? '—' }}</td>
                    <td class="px-4 py-3 font-medium">-{{ entry.points }}</td>
                  </tr></template
                ></DataTable
              >
              <div class="mt-4"><PaginationLinks :links="pointsRedeemed.links" /></div></CardContent
          ></Card>
        </div>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold">Profit</h2>
          <p class="text-sm text-muted-foreground">
            Estimated from booked sales less each product’s current cost price; unrecorded operating
            costs are excluded.
          </p>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
          <Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Revenue</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.profit.revenue) }}
              </p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Product cost</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.profit.cost) }}
              </p></CardContent
            ></Card
          ><Card
            ><CardHeader class="pb-2"
              ><CardTitle class="text-sm font-medium">Estimated profit</CardTitle></CardHeader
            ><CardContent
              ><p class="text-2xl font-semibold">
                {{ formatCurrency(summaries.profit.profit) }}
              </p></CardContent
            ></Card
          >
        </div>
        <Card
          ><CardContent class="pt-6"
            ><DataTable
              ><template #head
                ><tr>
                  <th class="px-4 py-3 font-medium">Product</th>
                  <th class="px-4 py-3 font-medium">Quantity</th>
                  <th class="px-4 py-3 font-medium">Revenue</th>
                  <th class="px-4 py-3 font-medium">Cost</th>
                  <th class="px-4 py-3 font-medium">Profit</th>
                </tr></template
              ><template #body
                ><tr v-if="profitByProduct.data.length === 0">
                  <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                    No booked sales match the current filters.
                  </td>
                </tr>
                <tr v-for="product in profitByProduct.data" :key="product.id">
                  <td class="px-4 py-3 font-medium">
                    {{ product.name }}
                    <p class="text-xs font-normal text-muted-foreground">{{ product.brand }}</p>
                  </td>
                  <td class="px-4 py-3">{{ product.quantity }}</td>
                  <td class="px-4 py-3">{{ formatCurrency(product.revenue) }}</td>
                  <td class="px-4 py-3">{{ formatCurrency(product.cost) }}</td>
                  <td class="px-4 py-3 font-medium">{{ formatCurrency(product.profit) }}</td>
                </tr></template
              ></DataTable
            >
            <div class="mt-4"><PaginationLinks :links="profitByProduct.links" /></div></CardContent
        ></Card>
      </section>
    </div>
  </AppLayout>
</template>
