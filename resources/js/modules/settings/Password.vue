<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import type { BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Password settings', href: route('password.edit') }]
const form = useForm({ current_password: '', password: '', password_confirmation: '' })

const submit = () =>
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
</script>

<template>
  <Head title="Password settings" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <SettingsLayout>
      <Card>
        <CardHeader>
          <CardTitle>Update password</CardTitle>
          <CardDescription
            >Use a long, unique password to keep your account secure.</CardDescription
          >
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <FormField
              id="current_password"
              label="Current password"
              :error="form.errors.current_password"
              required
            >
              <Input
                id="current_password"
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                required
              />
            </FormField>
            <FormField id="password" label="New password" :error="form.errors.password" required>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                required
              />
            </FormField>
            <FormField
              id="password_confirmation"
              label="Confirm password"
              :error="form.errors.password_confirmation"
              required
            >
              <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                required
              />
            </FormField>
            <Button :disabled="form.processing">Save password</Button>
          </form>
        </CardContent>
      </Card>
    </SettingsLayout>
  </AppLayout>
</template>
