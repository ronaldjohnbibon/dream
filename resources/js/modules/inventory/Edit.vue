<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import RiceProductForm from '@/modules/inventory/components/RiceProductForm.vue'
import type { RiceProduct } from '@/modules/inventory/types'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{ product: RiceProduct }>()
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Rice inventory', href: route('rice-products.index') },
  {
    title: props.product.name,
    href: route('rice-products.show', { riceProduct: props.product.id }),
  },
  { title: 'Edit', href: route('rice-products.edit', { riceProduct: props.product.id }) },
]

const form = useForm({
  name: props.product.name,
  brand: props.product.brand,
  description: props.product.description ?? '',
  cost_price: props.product.cost_price,
  selling_price: props.product.selling_price,
  initial_stock: 0,
})

const submit = () => form.put(route('rice-products.update', { riceProduct: props.product.id }))
</script>

<template>
  <Head title="Edit rice product" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
      <Card>
        <CardHeader><CardTitle>Edit rice product</CardTitle></CardHeader>
        <CardContent
          ><RiceProductForm :form="form" submit-label="Save changes" editing @submit="submit"
        /></CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
