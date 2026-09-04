<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{ user: { name: string; email: string } }>()
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Profile settings', href: route('profile.edit') }]
const form = useForm({ name: props.user.name, email: props.user.email })

const submit = () => form.patch(route('profile.update'), { preserveScroll: true })
</script>

<template>
  <Head title="Profile settings" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <SettingsLayout>
      <Card>
        <CardHeader>
          <CardTitle>Profile information</CardTitle>
          <CardDescription>Update your name and email address.</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <FormField id="name" label="Name" :error="form.errors.name" required>
              <Input id="name" v-model="form.name" autocomplete="name" required />
            </FormField>
            <FormField id="email" label="Email address" :error="form.errors.email" required>
              <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
            </FormField>
            <Button :disabled="form.processing">Save profile</Button>
          </form>
        </CardContent>
      </Card>
    </SettingsLayout>
  </AppLayout>
</template>
