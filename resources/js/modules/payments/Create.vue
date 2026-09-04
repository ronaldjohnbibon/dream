<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  order: { id: number; order_number: string; remaining_balance: string }
  installment: {
    id: number
    installment_number: number
    amount_due: string
    remaining_balance: string
  }
  gcash: { account_name: string; account_number: string; qr_code_url: string }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'My orders', href: route('orders.index') },
  { title: props.order.order_number, href: route('orders.show', { order: props.order.id }) },
  {
    title: 'Submit GCash payment',
    href: route('gcash-payments.create', { order: props.order.id }),
  },
])
const form = useForm({
  idempotency_key: crypto.randomUUID(),
  pautang_installment_id: props.installment.id,
  amount: '',
  reference_number: '',
  screenshot: null as File | null,
  payment_date: new Date().toISOString().slice(0, 10),
})
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const paymentLimit = computed(() => props.installment.remaining_balance)
const paymentError = computed(() => (form.errors as Record<string, string>).payment)

const submit = () =>
  form.post(route('gcash-payments.store', { order: props.order.id }), { forceFormData: true })
</script>

<template>
  <Head title="Submit GCash payment" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Submit GCash payment</h1>
          <p class="mt-1 text-sm text-muted-foreground">Order {{ order.order_number }}</p>
        </div>
        <Button class="w-full sm:w-auto" variant="outline" as-child
          ><Link :href="route('orders.show', { order: order.id })">Back to order</Link></Button
        >
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            ><CardTitle>Scan to pay</CardTitle
            ><CardDescription
              >Send to {{ gcash.account_name }} · {{ gcash.account_number }}, then upload your proof
              of payment.</CardDescription
            ></CardHeader
          >
          <CardContent
            ><img
              :src="gcash.qr_code_url"
              alt="GCash payment QR code"
              class="mx-auto max-h-96 rounded-lg border object-contain"
          /></CardContent>
        </Card>

        <Card>
          <CardHeader
            ><CardTitle>Payment details</CardTitle
            ><CardDescription
              >Give {{ installment.installment_number }} · Remaining
              {{ currency.format(Number(installment.remaining_balance)) }}</CardDescription
            ></CardHeader
          >
          <CardContent>
            <form class="space-y-5" @submit.prevent="submit">
              <FormField id="amount" label="Amount" :error="form.errors.amount" required>
                <input
                  id="amount"
                  v-model="form.amount"
                  type="number"
                  min="0.01"
                  :max="paymentLimit"
                  step="0.01"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  required
                />
              </FormField>
              <FormField
                id="reference-number"
                label="GCash reference number"
                :error="form.errors.reference_number"
                required
                ><input
                  id="reference-number"
                  v-model="form.reference_number"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  required
              /></FormField>
              <FormField
                id="payment-date"
                label="Payment date"
                :error="form.errors.payment_date"
                required
                ><input
                  id="payment-date"
                  v-model="form.payment_date"
                  type="date"
                  :max="new Date().toISOString().slice(0, 10)"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  required
              /></FormField>
              <FormField
                id="screenshot"
                label="Payment screenshot"
                :error="form.errors.screenshot"
                required
                ><input
                  id="screenshot"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="block w-full text-sm"
                  required
                  @change="
                    form.screenshot = ($event.target as HTMLInputElement).files?.[0] ?? null
                  "
              /></FormField>
              <p v-if="paymentError" class="text-sm text-destructive">{{ paymentError }}</p>
              <div
                class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-10 -mx-6 -mb-6 border-t bg-card/95 p-4 backdrop-blur sm:static sm:mx-0 sm:mb-0 sm:border-0 sm:bg-transparent sm:p-0"
              >
                <Button class="w-full sm:w-auto" type="submit" :disabled="form.processing"
                  >Submit for verification</Button
                >
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
