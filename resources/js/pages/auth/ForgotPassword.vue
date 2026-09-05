<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import FeedbackAlert from '@/components/shared/FeedbackAlert.vue'
import TextLink from '@/components/TextLink.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

defineProps<{
  status?: string
}>()

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>

<template>
  <AuthLayout
    title="Forgot password"
    description="Enter your email to receive a password reset link"
  >
    <Head title="Forgot password" />

    <FeedbackAlert v-if="status" variant="success" :messages="[status]" class="mb-4" />

    <div class="space-y-6">
      <form @submit.prevent="submit">
        <div class="grid gap-2">
          <Label for="email">Email address</Label>
          <Input
            id="email"
            type="email"
            name="email"
            autocomplete="off"
            v-model="form.email"
            autofocus
            placeholder="email@example.com"
          />
          <InputError :message="form.errors.email" />
        </div>

        <div class="my-6 flex items-center justify-start">
          <Button class="w-full" :loading="form.processing" loading-text="Sending link…"
            >Email password reset link</Button
          >
        </div>
      </form>

      <div class="space-x-1 text-center text-sm text-muted-foreground">
        <span>Or, return to</span>
        <TextLink :href="route('login')">log in</TextLink>
      </div>
    </div>
  </AuthLayout>
</template>
