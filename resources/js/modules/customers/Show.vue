<script setup lang="ts">
import ConfirmModal from '@/components/shared/ConfirmModal.vue'
import DataTable from '@/components/shared/DataTable.vue'
import FormField from '@/components/shared/FormField.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import type {
  Customer,
  CustomerHistoryPagination,
  CustomerOrderHistory,
  CustomerPaymentHistory,
  CustomerPointsHistory,
  CustomerStatus,
  CustomerSummary,
} from '@/modules/customers/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  customer: Customer
  summary: CustomerSummary
  orders: CustomerHistoryPagination<CustomerOrderHistory>
  payments: CustomerHistoryPagination<CustomerPaymentHistory>
  pointsHistory: CustomerHistoryPagination<CustomerPointsHistory>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Customers', href: route('customers.index') },
  { title: props.customer.name, href: route('customers.show', { customer: props.customer.id }) },
]

const suspending = ref(false)
const suspendDialogOpen = ref(false)
const reactivating = ref(false)
const adjustment = useForm({ idempotency_key: crypto.randomUUID(), points: 0, reason: '' })
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' })
const formattedDate = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})

const statusLabel = computed(
  () =>
    ({
      good_standing: 'Good Standing',
      overdue: 'Overdue',
      suspended: 'Suspended',
    })[props.customer.account_status as CustomerStatus]
)
const statusClass = computed(
  () =>
    ({
      good_standing: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
      overdue: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
      suspended: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    })[props.customer.account_status as CustomerStatus]
)

const paymentTypeLabel = () => 'Pautang'
const paymentStatusLabel = (status: CustomerOrderHistory['payment_status']) =>
  ({ unpaid: 'Unpaid', partially_paid: 'Partially Paid', paid: 'Paid', overdue: 'Overdue' })[status]
const orderStatusLabel = (status: CustomerOrderHistory['order_status']) =>
  ({
    pending: 'Pending',
    confirmed: 'Confirmed',
    preparing: 'Preparing',
    out_for_delivery: 'Out for Delivery',
    delivered: 'Delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
  })[status]
const paymentReviewStatusLabel = (status: CustomerPaymentHistory['status']) =>
  ({ pending_verification: 'Pending Verification', approved: 'Approved', rejected: 'Rejected' })[
    status
  ]
const pointsTypeLabel = (type: CustomerPointsHistory['type']) =>
  ({
    order_reward: 'Completed Pautang Reward',
    on_time_payment_bonus: 'On-Time Payment Bonus',
    redemption: 'Redemption',
    redemption_refund: 'Redemption Refund',
    admin_adjustment: 'Admin Adjustment',
  })[type]

const paymentStatusClass = (status: CustomerOrderHistory['payment_status']) =>
  ({
    unpaid: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
    partially_paid: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    overdue: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  })[status]
const paymentReviewStatusClass = (status: CustomerPaymentHistory['status']) =>
  ({
    pending_verification: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
  })[status]

const suspendCustomer = () => {
  suspending.value = true
  router.patch(
    route('customers.suspend', { customer: props.customer.id }),
    {},
    {
      onFinish: () => {
        suspending.value = false
      },
      onSuccess: () => {
        suspendDialogOpen.value = false
      },
    }
  )
}

const reactivateCustomer = () => {
  reactivating.value = true
  router.patch(
    route('customers.reactivate', { customer: props.customer.id }),
    {},
    {
      onFinish: () => {
        reactivating.value = false
      },
    }
  )
}

const submitAdjustment = () => {
  adjustment.post(route('customers.points.adjustments.store', { customer: props.customer.id }), {
    onSuccess: () => {
      adjustment.reset()
      adjustment.idempotency_key = crypto.randomUUID()
    },
  })
}
</script>

<template>
  <Head :title="customer.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ customer.name }}</h1>
            <span class="rounded-full px-2 py-1 text-xs" :class="statusClass">{{
              statusLabel
            }}</span>
          </div>
          <p class="mt-1 text-sm text-muted-foreground">Customer profile and account activity</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="customer.account_status !== 'suspended'"
            variant="destructive"
            @click="suspendDialogOpen = true"
            >Suspend customer</Button
          >
          <Button
            v-else
            variant="outline"
            :loading="reactivating"
            loading-text="Reactivating…"
            @click="reactivateCustomer"
            >Reactivate customer</Button
          >
          <Button as-child
            ><Link :href="route('customers.edit', { customer: customer.id })"
              >Edit customer</Link
            ></Button
          >
        </div>
      </div>

      <Card>
        <CardHeader><CardTitle>Customer information</CardTitle></CardHeader>
        <CardContent class="grid gap-5 text-sm sm:grid-cols-2">
          <div>
            <p class="text-muted-foreground">Email</p>
            <p class="mt-1 font-medium">{{ customer.email || 'Not provided' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Mobile number</p>
            <p class="mt-1 font-medium">{{ customer.mobile_number || 'Not provided' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Delivery area</p>
            <p class="mt-1 font-medium">{{ customer.delivery_area || 'Not provided' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Account status</p>
            <p class="mt-1 font-medium">{{ statusLabel }}</p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-muted-foreground">Complete address</p>
            <p class="mt-1 whitespace-pre-line font-medium">
              {{ customer.complete_address || 'Not provided' }}
            </p>
          </div>
          <div>
            <p class="text-muted-foreground">Created</p>
            <p class="mt-1 font-medium">
              {{ formattedDate.format(new Date(customer.created_at)) }}
            </p>
          </div>
          <div>
            <p class="text-muted-foreground">Last updated</p>
            <p class="mt-1 font-medium">
              {{ formattedDate.format(new Date(customer.updated_at)) }}
            </p>
          </div>
        </CardContent>
      </Card>

      <section
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        aria-label="Customer account summary"
      >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Current points</CardDescription
            ><CardTitle class="text-2xl">{{ summary.current_points }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Peso equivalent</CardDescription
            ><CardTitle class="text-2xl">{{
              currency.format(Number(summary.peso_equivalent))
            }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Total orders</CardDescription
            ><CardTitle class="text-2xl">{{ summary.total_orders }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Outstanding balance</CardDescription
            ><CardTitle class="text-2xl">{{
              currency.format(summary.outstanding_balance)
            }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Active pautang</CardDescription
            ><CardTitle class="text-2xl">{{ summary.active_pautang }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Completed pautang</CardDescription
            ><CardTitle class="text-2xl">{{ summary.completed_pautang }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>On-time payments</CardDescription
            ><CardTitle class="text-2xl">{{ summary.on_time_payments }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Late payments</CardDescription
            ><CardTitle class="text-2xl">{{ summary.late_payments }}</CardTitle></CardHeader
          ></Card
        >
      </section>

      <Card>
        <CardHeader
          ><CardTitle>Adjust points</CardTitle
          ><CardDescription
            >Add a positive amount or enter a negative amount to deduct points. The balance cannot
            go below zero.</CardDescription
          ></CardHeader
        >
        <CardContent>
          <form
            class="grid gap-5 sm:grid-cols-[180px_1fr_auto] sm:items-end"
            @submit.prevent="submitAdjustment"
          >
            <FormField
              id="points-change"
              label="Points change"
              :error="adjustment.errors.points"
              required
              ><Input
                id="points-change"
                v-model.number="adjustment.points"
                type="number"
                step="1"
                required
            /></FormField>
            <FormField
              id="adjustment-reason"
              label="Reason"
              :error="adjustment.errors.reason"
              required
              ><Input id="adjustment-reason" v-model="adjustment.reason" maxlength="1000" required
            /></FormField>
            <Button
              type="submit"
              :loading="adjustment.processing"
              loading-text="Recording adjustment…"
              >Record adjustment</Button
            >
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          ><CardTitle>Order history</CardTitle
          ><CardDescription>All orders placed by this customer.</CardDescription></CardHeader
        >
        <CardContent class="space-y-4">
          <DataTable>
            <template #head
              ><tr>
                <th class="px-4 py-3 font-medium">Order</th>
                <th class="px-4 py-3 font-medium">Date</th>
                <th class="px-4 py-3 font-medium">Product</th>
                <th class="px-4 py-3 font-medium">Payment</th>
                <th class="px-4 py-3 font-medium">Order status</th>
                <th class="px-4 py-3 text-right font-medium">Amount</th>
                <th class="px-4 py-3 text-right font-medium">Balance</th>
              </tr></template
            >
            <template #body>
              <tr v-if="orders.data.length === 0">
                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                  No orders yet.
                </td>
              </tr>
              <tr v-for="order in orders.data" :key="order.id">
                <td class="px-4 py-3 font-medium">
                  <Link class="hover:underline" :href="route('orders.show', { order: order.id })">{{
                    order.order_number
                  }}</Link>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                  {{ dateFormatter.format(new Date(`${order.order_date}T00:00:00`)) }}
                </td>
                <td class="px-4 py-3">
                  <p>{{ order.product_name }}</p>
                  <p v-if="order.sack_size" class="text-xs text-muted-foreground">
                    {{ order.quantity }} × {{ order.sack_size }}
                  </p>
                </td>
                <td class="px-4 py-3">
                  <p>{{ paymentTypeLabel() }}</p>
                  <span
                    class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs"
                    :class="paymentStatusClass(order.payment_status)"
                    >{{ paymentStatusLabel(order.payment_status) }}</span
                  >
                </td>
                <td class="px-4 py-3">{{ orderStatusLabel(order.order_status) }}</td>
                <td class="px-4 py-3 text-right font-medium">
                  {{ currency.format(Number(order.final_amount)) }}
                </td>
                <td class="px-4 py-3 text-right">
                  {{ currency.format(Number(order.remaining_balance)) }}
                </td>
              </tr>
            </template>
          </DataTable>
          <div class="flex justify-end"><PaginationLinks :links="orders.links" /></div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          ><CardTitle>Payment history</CardTitle
          ><CardDescription
            >All submitted GCash payments, including pending and rejected records.</CardDescription
          ></CardHeader
        >
        <CardContent class="space-y-4">
          <DataTable>
            <template #head
              ><tr>
                <th class="px-4 py-3 font-medium">Date</th>
                <th class="px-4 py-3 font-medium">Order</th>
                <th class="px-4 py-3 font-medium">Type</th>
                <th class="px-4 py-3 font-medium">Installment</th>
                <th class="px-4 py-3 text-right font-medium">Amount</th>
                <th class="px-4 py-3 font-medium">Status</th>
              </tr></template
            >
            <template #body>
              <tr v-if="payments.data.length === 0">
                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                  No payment submissions yet.
                </td>
              </tr>
              <tr v-for="payment in payments.data" :key="payment.id">
                <td class="px-4 py-3 text-muted-foreground">
                  {{ dateFormatter.format(new Date(`${payment.payment_date}T00:00:00`)) }}
                </td>
                <td class="px-4 py-3 font-medium">
                  <Link
                    class="hover:underline"
                    :href="route('gcash-payments.show', { gcashPayment: payment.id })"
                    >{{ payment.order.order_number }}</Link
                  >
                </td>
                <td class="px-4 py-3">{{ paymentTypeLabel() }}</td>
                <td class="px-4 py-3">
                  {{ payment.installment_number ? `Give ${payment.installment_number}` : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-medium">
                  {{ currency.format(Number(payment.amount)) }}
                </td>
                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-2 py-1 text-xs"
                    :class="paymentReviewStatusClass(payment.status)"
                    >{{ paymentReviewStatusLabel(payment.status) }}</span
                  >
                </td>
              </tr>
            </template>
          </DataTable>
          <div class="flex justify-end"><PaginationLinks :links="payments.links" /></div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          ><CardTitle>Points history</CardTitle
          ><CardDescription>Every earned, redeemed, or adjusted point.</CardDescription></CardHeader
        >
        <CardContent class="space-y-4">
          <DataTable>
            <template #head
              ><tr>
                <th class="px-4 py-3 font-medium">Date</th>
                <th class="px-4 py-3 font-medium">Type</th>
                <th class="px-4 py-3 font-medium">Description</th>
                <th class="px-4 py-3 font-medium">Related order</th>
                <th class="px-4 py-3 text-right font-medium">Points</th>
              </tr></template
            >
            <template #body>
              <tr v-if="pointsHistory.data.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                  No points transactions yet.
                </td>
              </tr>
              <tr v-for="entry in pointsHistory.data" :key="entry.id">
                <td class="px-4 py-3 text-muted-foreground">
                  {{ dateFormatter.format(new Date(`${entry.transaction_date}T00:00:00`)) }}
                </td>
                <td class="px-4 py-3">{{ pointsTypeLabel(entry.type) }}</td>
                <td class="px-4 py-3 text-muted-foreground">{{ entry.description }}</td>
                <td class="px-4 py-3">
                  <Link
                    v-if="entry.order"
                    class="hover:underline"
                    :href="route('orders.show', { order: entry.order.id })"
                    >{{ entry.order.order_number }}</Link
                  ><span v-else class="text-muted-foreground">—</span>
                </td>
                <td
                  class="px-4 py-3 text-right font-medium"
                  :class="
                    entry.points > 0
                      ? 'text-emerald-700 dark:text-emerald-400'
                      : 'text-red-700 dark:text-red-400'
                  "
                >
                  {{ entry.points > 0 ? '+' : '' }}{{ entry.points }}
                </td>
              </tr>
            </template>
          </DataTable>
          <div class="flex justify-end"><PaginationLinks :links="pointsHistory.links" /></div>
        </CardContent>
      </Card>
    </div>

    <ConfirmModal
      :open="suspendDialogOpen"
      title="Suspend customer"
      :description="`Suspend ${customer.name}? They will no longer be able to access the application.`"
      confirm-label="Suspend customer"
      :processing="suspending"
      variant="warning"
      @confirm="suspendCustomer"
      @close="suspendDialogOpen = false"
    />
  </AppLayout>
</template>
