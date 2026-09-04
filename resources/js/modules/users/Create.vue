<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import UserForm from '@/modules/users/components/UserForm.vue'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Users', href: route('users.index') },
  { title: 'Create administrator', href: route('users.create') },
]

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const submit = () => form.post(route('users.store'))
</script>

<template>
  <Head title="Create administrator" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
      <Card>
        <CardHeader><CardTitle>Create administrator</CardTitle></CardHeader>
        <CardContent
          ><UserForm :form="form" submit-label="Create administrator" @submit="submit"
        /></CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
