<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import FormField from '@/components/shared/FormField.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import type { PaginatedPointsLedger, PointsLedgerType } from '@/modules/points/types'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{
  customer: { id: number; name: string }
  balance: number
  pesoEquivalent: string
  pesoPerPoint: string
  ledger: PaginatedPointsLedger
  canAdjust: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = props.canAdjust
  ? [
      { title: 'Customers', href: route('customers.index') },
      {
        title: props.customer.name,
        href: route('customers.show', { customer: props.customer.id }),
      },
      { title: 'Points', href: route('customers.points.show', { customer: props.customer.id }) },
    ]
  : [{ title: 'My points', href: route('points.show') }]

const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' })
const adjustment = useForm({ idempotency_key: crypto.randomUUID(), points: 0, reason: '' })

const typeLabel = (type: PointsLedgerType) =>
  ({
    order_reward: 'Completed Pautang Reward',
    on_time_payment_bonus: 'On-Time Payment Bonus',
    redemption: 'Redemption',
    redemption_refund: 'Redemption Refund',
    admin_adjustment: 'Admin Adjustment',
  })[type]

const submitAdjustment = () => {
  adjustment.post(route('customers.points.adjustments.store', { customer: props.customer.id }), {
    onSuccess: () => adjustment.reset(),
  })
}
</script>

<template>
  <Head :title="canAdjust ? `${customer.name} points` : 'My points'" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">
          {{ canAdjust ? `${customer.name}'s points` : 'My points' }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Your balance is calculated from your complete points history.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Current points</CardDescription
            ><CardTitle class="text-3xl">{{ balance }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Peso equivalent</CardDescription
            ><CardTitle class="text-3xl">{{
              currency.format(Number(pesoEquivalent))
            }}</CardTitle></CardHeader
          ></Card
        >
        <Card
          ><CardHeader class="pb-2"
            ><CardDescription>Conversion rate</CardDescription
            ><CardTitle class="text-3xl">{{
              currency.format(Number(pesoPerPoint))
            }}</CardTitle></CardHeader
          ><CardContent class="text-sm text-muted-foreground">per point</CardContent></Card
        >
      </div>

      <Card v-if="canAdjust">
        <CardHeader
          ><CardTitle>Admin adjustment</CardTitle
          ><CardDescription
            >Enter a positive value to add points or a negative value to deduct them. The balance
            cannot go below zero.</CardDescription
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
            <Button type="submit" :disabled="adjustment.processing">Record adjustment</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          ><CardTitle>Points history</CardTitle
          ><CardDescription
            >Every earned, redeemed, or adjusted point is listed here.</CardDescription
          ></CardHeader
        >
        <CardContent class="space-y-4">
          <div v-if="!canAdjust" class="space-y-3 md:hidden">
            <p
              v-if="ledger.data.length === 0"
              class="py-8 text-center text-sm text-muted-foreground"
            >
              No points transactions yet.
            </p>
            <div v-for="entry in ledger.data" :key="entry.id" class="rounded-lg border p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-medium">{{ typeLabel(entry.type) }}</p>
                  <p class="mt-1 text-xs text-muted-foreground">
                    {{ dateFormatter.format(new Date(`${entry.transaction_date}T00:00:00`)) }}
                  </p>
                </div>
                <p
                  class="font-semibold"
                  :class="
                    entry.points > 0
                      ? 'text-emerald-700 dark:text-emerald-400'
                      : 'text-red-700 dark:text-red-400'
                  "
                >
                  {{ entry.points > 0 ? '+' : '' }}{{ entry.points }}
                </p>
              </div>
              <p class="mt-3 text-sm text-muted-foreground">{{ entry.description }}</p>
              <p v-if="entry.order" class="mt-2 text-xs text-muted-foreground">
                Order {{ entry.order.order_number }}
              </p>
            </div>
          </div>
          <div :class="!canAdjust ? 'hidden md:block' : ''">
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
                <tr v-if="ledger.data.length === 0">
                  <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                    No points transactions yet.
                  </td>
                </tr>
                <tr v-for="entry in ledger.data" :key="entry.id">
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ dateFormatter.format(new Date(`${entry.transaction_date}T00:00:00`)) }}
                  </td>
                  <td class="px-4 py-3">{{ typeLabel(entry.type) }}</td>
                  <td class="px-4 py-3 text-muted-foreground">{{ entry.description }}</td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ entry.order?.order_number ?? '—' }}
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
          </div>
          <div class="flex justify-end text-sm text-muted-foreground">
            <PaginationLinks :links="ledger.links" />
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
