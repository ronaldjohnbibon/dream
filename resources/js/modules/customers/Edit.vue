<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import CustomerForm from '@/modules/customers/components/CustomerForm.vue'
import type { Customer } from '@/modules/customers/types'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{ customer: Customer }>()
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Customers', href: route('customers.index') },
  { title: props.customer.name, href: route('customers.show', { customer: props.customer.id }) },
  { title: 'Edit', href: route('customers.edit', { customer: props.customer.id }) },
]

const form = useForm({
  name: props.customer.name,
  email: props.customer.email ?? '',
  mobile_number: props.customer.mobile_number ?? '',
  complete_address: props.customer.complete_address ?? '',
  delivery_area: props.customer.delivery_area ?? '',
  password: '',
  password_confirmation: '',
})

const submit = () => form.put(route('customers.update', { customer: props.customer.id }))
</script>

<template>
  <Head :title="`Edit ${customer.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
      <Card>
        <CardHeader><CardTitle>Edit customer</CardTitle></CardHeader>
        <CardContent
          ><CustomerForm :form="form" submit-label="Save changes" editing @submit="submit"
        /></CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
