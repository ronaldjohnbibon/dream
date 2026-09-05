<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  deliveryStatusLabels,
  type DeliveryArea,
  type DeliveryStatus,
} from '@/modules/orders/types'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
type DeliveryDetail = {
  id: number
  order: { id: number; order_number: string; quantity: number; final_amount: string }
  customer: { name: string; email: string | null; mobile_number: string | null }
  delivery_area_id: number
  delivery_area_name: string
  delivery_address: string
  delivery_fee: string
  delivery_date: string | null
  delivery_person: string | null
  status: DeliveryStatus
  notes: string | null
  delivered_date: string | null
  rice_product: { name: string; brand: string } | null
}
const props = defineProps<{
  delivery: DeliveryDetail
  canManage: boolean
  allowedStatuses: DeliveryStatus[]
  areas: DeliveryArea[]
}>()
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Deliveries', href: route('deliveries.index') },
  {
    title: props.delivery.order.order_number,
    href: route('deliveries.show', { delivery: props.delivery.id }),
  },
])
const form = useForm({
  delivery_area_id: props.delivery.delivery_area_id,
  delivery_address: props.delivery.delivery_address,
  delivery_date: props.delivery.delivery_date ?? '',
  delivery_person: props.delivery.delivery_person ?? '',
  status: props.delivery.status,
  notes: props.delivery.notes ?? '',
})
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
</script>
<template>
  <Head :title="`Delivery ${delivery.order.order_number}`" /><AppLayout :breadcrumbs="breadcrumbs"
    ><div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
      <div class="flex justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">{{ delivery.order.order_number }}</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ delivery.customer.name }} ·
            {{ delivery.customer.mobile_number ?? delivery.customer.email }}
          </p>
        </div>
        <Button variant="outline" as-child
          ><Link :href="route('orders.show', { order: delivery.order.id })"
            >View order</Link
          ></Button
        >
      </div>
      <Card
        ><CardHeader><CardTitle>Delivery</CardTitle></CardHeader
        ><CardContent v-if="canManage"
          ><form
            class="grid gap-5 sm:grid-cols-2"
            @submit.prevent="form.patch(route('deliveries.update', { delivery: delivery.id }))"
          >
            <FormField
              id="area"
              label="Delivery area"
              :error="form.errors.delivery_area_id"
              required
              ><select
                id="area"
                v-model.number="form.delivery_area_id"
                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              >
                <option v-for="area in areas" :key="area.id" :value="area.id">
                  {{ area.name }} · {{ currency.format(Number(area.delivery_fee)) }}
                </option>
              </select></FormField
            ><FormField id="status" label="Status" :error="form.errors.status" required
              ><select
                id="status"
                v-model="form.status"
                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              >
                <option v-for="status in allowedStatuses" :key="status" :value="status">
                  {{ deliveryStatusLabels[status] }}
                </option>
              </select></FormField
            ><FormField id="date" label="Delivery date" :error="form.errors.delivery_date"
              ><input
                id="date"
                v-model="form.delivery_date"
                type="date"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></FormField
            ><FormField id="person" label="Delivery person" :error="form.errors.delivery_person"
              ><input
                id="person"
                v-model="form.delivery_person"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></FormField
            ><FormField
              id="address"
              label="Delivery address"
              :error="form.errors.delivery_address"
              required
              class="sm:col-span-2"
            >
              <textarea
                id="address"
                v-model="form.delivery_address"
                rows="3"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              /></FormField
            ><FormField id="notes" label="Notes" :error="form.errors.notes" class="sm:col-span-2">
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              />
            </FormField>
            <div class="sm:col-span-2">
              <Button type="submit" :loading="form.processing" loading-text="Saving delivery…"
                >Save delivery</Button
              >
            </div>
          </form></CardContent
        ><CardContent v-else class="grid gap-4 text-sm sm:grid-cols-2"
          ><div>
            <p class="text-muted-foreground">Status</p>
            <p class="font-medium">{{ deliveryStatusLabels[delivery.status] }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Delivery date</p>
            <p class="font-medium">{{ delivery.delivery_date ?? 'Not scheduled' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Area</p>
            <p class="font-medium">{{ delivery.delivery_area_name }}</p>
          </div>
          <div>
            <p class="text-muted-foreground">Fee</p>
            <p class="font-medium">{{ currency.format(Number(delivery.delivery_fee)) }}</p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-muted-foreground">Address</p>
            <p class="whitespace-pre-line font-medium">{{ delivery.delivery_address }}</p>
          </div></CardContent
        ></Card
      >
    </div></AppLayout
  >
</template>
