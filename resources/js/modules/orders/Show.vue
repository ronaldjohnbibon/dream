<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  deliveryStatusLabels,
  gcashPaymentStatusLabels,
  paymentStatusLabels,
  type DeliveryStatus,
  type GcashPaymentStatus,
  type Order,
  type PautangInstallmentStatus,
} from '@/modules/orders/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
const props = defineProps<{ order: Order; canManage: boolean }>()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: props.canManage ? 'Orders' : 'My orders', href: route('orders.index') },
  { title: props.order.order_number, href: route('orders.show', { order: props.order.id }) },
])
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const isCustomer = computed(() => !props.canManage)
const deliveryClass = (status: DeliveryStatus) =>
  ({
    pending: 'bg-amber-100 text-amber-800',
    scheduled: 'bg-sky-100 text-sky-800',
    preparing: 'bg-violet-100 text-violet-800',
    out_for_delivery: 'bg-indigo-100 text-indigo-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-800',
  })[status]
const paymentClass = (status: GcashPaymentStatus) =>
  ({
    pending_verification: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
  })[status]
const installmentLabel = (status: PautangInstallmentStatus) =>
  ({ pending: 'Pending', partially_paid: 'Partially Paid', paid: 'Paid', overdue: 'Overdue' })[
    status
  ]
</script>
<template>
  <Head :title="order.order_number" /><AppLayout :breadcrumbs="breadcrumbs"
    ><div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">{{ order.order_number }}</h1>
            <span
              v-if="order.delivery"
              class="rounded-full px-2 py-1 text-xs"
              :class="deliveryClass(order.delivery.status)"
              >{{ deliveryStatusLabels[order.delivery.status] }}</span
            >
          </div>
          <p class="mt-1 text-sm text-muted-foreground">Ordered on {{ order.order_date }}</p>
        </div>
        <Button class="w-full sm:w-auto" variant="outline" as-child
          ><Link :href="route('orders.index')">Back to orders</Link></Button
        >
      </div>
      <div class="grid gap-4 sm:grid-cols-4">
        <Card
          ><CardHeader class="pb-2"
            ><CardTitle class="text-sm font-medium text-muted-foreground"
              >Rice subtotal</CardTitle
            ></CardHeader
          ><CardContent
            ><p class="text-xl font-semibold">
              {{ currency.format(Number(order.subtotal)) }}
            </p></CardContent
          ></Card
        ><Card
          ><CardHeader class="pb-2"
            ><CardTitle class="text-sm font-medium text-muted-foreground"
              >Delivery fee</CardTitle
            ></CardHeader
          ><CardContent
            ><p class="text-xl font-semibold">
              {{ currency.format(Number(order.delivery_fee)) }}
            </p></CardContent
          ></Card
        ><Card
          ><CardHeader class="pb-2"
            ><CardTitle class="text-sm font-medium text-muted-foreground"
              >Final amount</CardTitle
            ></CardHeader
          ><CardContent
            ><p class="text-xl font-semibold">
              {{ currency.format(Number(order.final_amount)) }}
            </p></CardContent
          ></Card
        ><Card
          ><CardHeader class="pb-2"
            ><CardTitle class="text-sm font-medium text-muted-foreground"
              >Payment status</CardTitle
            ></CardHeader
          ><CardContent
            ><p class="font-semibold">Pautang</p>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ paymentStatusLabels[order.payment_status] }}
            </p></CardContent
          ></Card
        >
      </div>
      <Card v-if="order.delivery"
        ><CardHeader><CardTitle>Delivery details</CardTitle></CardHeader
        ><CardContent class="grid gap-5 text-sm sm:grid-cols-2"
          ><div>
            <p class="text-muted-foreground">Delivery status</p>
            <p class="mt-1 font-medium">{{ deliveryStatusLabels[order.delivery.status] }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Delivery date</p>
            <p class="mt-1 font-medium">{{ order.delivery.delivery_date ?? 'Not scheduled' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Delivery area</p>
            <p class="mt-1 font-medium">{{ order.delivery.delivery_area_name }}</p>
          </div>
          <div v-if="order.delivery.delivery_person">
            <p class="text-muted-foreground">Delivery person</p>
            <p class="mt-1 font-medium">{{ order.delivery.delivery_person }}</p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-muted-foreground">Delivery address</p>
            <p class="mt-1 whitespace-pre-line font-medium">
              {{ order.delivery.delivery_address }}
            </p>
          </div>
          <div v-if="order.delivery.notes" class="sm:col-span-2">
            <p class="text-muted-foreground">Notes</p>
            <p class="mt-1 whitespace-pre-line font-medium">{{ order.delivery.notes }}</p>
          </div>
          <div v-if="order.delivery.delivered_date">
            <p class="text-muted-foreground">Delivered date</p>
            <p class="mt-1 font-medium">{{ order.delivery.delivered_date }}</p>
          </div>
          <div v-if="canManage" class="sm:col-span-2">
            <Button as-child
              ><Link :href="route('deliveries.show', { delivery: order.delivery.id })"
                >Manage delivery</Link
              ></Button
            >
          </div></CardContent
        ></Card
      >
      <Card v-if="Number(order.points_discount) > 0"
        ><CardHeader><CardTitle>Points redemption</CardTitle></CardHeader
        ><CardContent class="grid gap-4 text-sm sm:grid-cols-2"
          ><div>
            <p class="text-muted-foreground">Points used</p>
            <p class="mt-1 font-medium">{{ order.points_used }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Points discount</p>
            <p class="mt-1 font-medium">{{ currency.format(Number(order.points_discount)) }}</p>
          </div></CardContent
        ></Card
      >
      <Card v-if="order.pautang_installments.length"
        ><CardHeader><CardTitle>Pautang installments</CardTitle></CardHeader
        ><CardContent class="space-y-3"
          ><div
            v-for="installment in order.pautang_installments"
            :key="installment.id"
            class="rounded-lg border p-4"
          >
            <div class="flex justify-between">
              <p class="font-medium">Give {{ installment.installment_number }}</p>
              <span>{{ installmentLabel(installment.status) }}</span>
            </div>
            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-4">
              <div>
                <p class="text-muted-foreground">Due</p>
                <p>{{ currency.format(Number(installment.amount_due)) }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Due date</p>
                <p>{{ installment.due_date }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Paid</p>
                <p>{{ currency.format(Number(installment.amount_paid)) }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Remaining</p>
                <p>{{ currency.format(Number(installment.remaining_balance)) }}</p>
              </div>
            </div>
            <Button
              v-if="
                isCustomer &&
                Number(installment.remaining_balance) > 0 &&
                order.delivery?.status !== 'pending' &&
                order.delivery?.status !== 'cancelled'
              "
              class="mt-4 w-full sm:w-auto"
              as-child
              ><Link
                :href="
                  route('gcash-payments.create', { order: order.id, installment: installment.id })
                "
                >Submit GCash payment</Link
              ></Button
            >
          </div></CardContent
        ></Card
      >
      <Card v-if="order.gcash_payments.length"
        ><CardHeader><CardTitle>GCash payment submissions</CardTitle></CardHeader
        ><CardContent class="space-y-3"
          ><div
            v-for="payment in order.gcash_payments"
            :key="payment.id"
            class="flex justify-between gap-3 rounded-lg border p-4"
          >
            <div class="text-sm">
              <p class="font-medium">
                {{ currency.format(Number(payment.amount)) }}
                <span
                  class="ml-2 rounded-full px-2 py-1 text-xs"
                  :class="paymentClass(payment.status)"
                  >{{ gcashPaymentStatusLabels[payment.status] }}</span
                >
              </p>
              <p class="mt-1 font-mono text-xs">Reference: {{ payment.reference_number }}</p>
              <p v-if="payment.remarks" class="mt-2 text-muted-foreground">{{ payment.remarks }}</p>
            </div>
            <a :href="payment.screenshot_url" target="_blank" class="text-sm text-primary underline"
              >View screenshot</a
            >
          </div></CardContent
        ></Card
      >
    </div></AppLayout
  >
</template>
