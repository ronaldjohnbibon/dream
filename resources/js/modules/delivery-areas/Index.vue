<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import type { DeliveryArea } from '@/modules/orders/types'
import type { BreadcrumbItem } from '@/types'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
const props = defineProps<{ areas: Required<DeliveryArea>[] }>()
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Delivery areas', href: route('delivery-areas.index') },
]
const createForm = useForm({ name: '', delivery_fee: '0.00' })
const editing = ref<number | null>(null)
const editForm = useForm({ name: '', delivery_fee: '' })
const startEdit = (area: Required<DeliveryArea>) => {
  editing.value = area.id
  editForm.name = area.name
  editForm.delivery_fee = area.delivery_fee
}
const saveEdit = (area: Required<DeliveryArea>) =>
  editForm.put(route('delivery-areas.update', { delivery_area: area.id }), {
    onSuccess: () => {
      editing.value = null
    },
  })
const toggle = (area: Required<DeliveryArea>) =>
  router.patch(route('delivery-areas.status', { deliveryArea: area.id }), {
    is_active: !area.is_active,
  })
const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
</script>
<template>
  <Head title="Delivery areas" /><AppLayout :breadcrumbs="breadcrumbs"
    ><div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Delivery areas</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Set the flat delivery fee for each supported area.
        </p>
      </div>
      <Card
        ><CardHeader><CardTitle>Add delivery area</CardTitle></CardHeader
        ><CardContent
          ><form
            class="grid gap-4 sm:grid-cols-[1fr_180px_auto]"
            @submit.prevent="
              createForm.post(route('delivery-areas.store'), {
                onSuccess: () => createForm.reset(),
              })
            "
          >
            <FormField id="name" label="Area name" :error="createForm.errors.name" required
              ><Input id="name" v-model="createForm.name" required /></FormField
            ><FormField
              id="fee"
              label="Delivery fee"
              :error="createForm.errors.delivery_fee"
              required
              ><Input
                id="fee"
                v-model="createForm.delivery_fee"
                type="number"
                min="0"
                step="0.01"
                required
            /></FormField>
            <div class="self-end">
              <Button type="submit" :disabled="createForm.processing">Add area</Button>
            </div>
          </form></CardContent
        ></Card
      ><Card
        ><CardHeader><CardTitle>Configured areas</CardTitle></CardHeader
        ><CardContent class="space-y-3"
          ><div
            v-for="area in areas"
            :key="area.id"
            class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_160px_auto_auto] sm:items-end"
          >
            <template v-if="editing === area.id"
              ><FormField :id="`name-${area.id}`" label="Area name" :error="editForm.errors.name"
                ><Input :id="`name-${area.id}`" v-model="editForm.name" /></FormField
              ><FormField
                :id="`fee-${area.id}`"
                label="Delivery fee"
                :error="editForm.errors.delivery_fee"
                ><Input
                  :id="`fee-${area.id}`"
                  v-model="editForm.delivery_fee"
                  type="number"
                  min="0"
                  step="0.01" /></FormField
              ><Button :disabled="editForm.processing" @click="saveEdit(area)">Save</Button
              ><Button variant="outline" @click="editing = null">Cancel</Button></template
            ><template v-else
              ><div>
                <p class="font-medium">{{ area.name }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ area.is_active ? 'Active' : 'Inactive' }}
                </p>
              </div>
              <p class="font-medium">{{ currency.format(Number(area.delivery_fee)) }}</p>
              <Button variant="outline" @click="startEdit(area)">Edit</Button
              ><Button :variant="area.is_active ? 'secondary' : 'default'" @click="toggle(area)">{{
                area.is_active ? 'Deactivate' : 'Activate'
              }}</Button></template
            >
          </div></CardContent
        ></Card
      >
    </div></AppLayout
  >
</template>
