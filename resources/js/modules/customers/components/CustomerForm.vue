<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { CustomerFormData } from '@/modules/customers/types'

interface CustomerFormState extends CustomerFormData {
  processing: boolean
  errors: Partial<Record<keyof CustomerFormData, string>>
}

defineProps<{
  form: CustomerFormState
  submitLabel: string
  editing?: boolean
}>()

const emit = defineEmits<{
  submit: []
}>()
</script>

<template>
  <form class="space-y-6" @submit.prevent="emit('submit')">
    <FormField id="name" label="Name" :error="form.errors.name" required>
      <Input id="name" v-model="form.name" autocomplete="name" required autofocus />
    </FormField>

    <FormField id="email" label="Email address" :error="form.errors.email">
      <Input id="email" v-model="form.email" type="email" autocomplete="email" />
    </FormField>

    <FormField id="mobile_number" label="Mobile number" :error="form.errors.mobile_number" required>
      <Input
        id="mobile_number"
        v-model="form.mobile_number"
        type="tel"
        autocomplete="tel"
        required
      />
    </FormField>

    <FormField
      id="complete_address"
      label="Complete address"
      :error="form.errors.complete_address"
      required
    >
      <textarea
        id="complete_address"
        v-model="form.complete_address"
        rows="3"
        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring"
        required
      />
    </FormField>

    <FormField id="delivery_area" label="Delivery area" :error="form.errors.delivery_area" required>
      <Input id="delivery_area" v-model="form.delivery_area" required />
    </FormField>

    <FormField
      id="password"
      :label="editing ? 'New password' : 'Password'"
      :error="form.errors.password"
      :required="!editing"
    >
      <Input
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
        :required="!editing"
      />
    </FormField>

    <FormField
      id="password_confirmation"
      label="Confirm password"
      :error="form.errors.password_confirmation"
      :required="!editing"
    >
      <Input
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        :required="!editing"
      />
    </FormField>

    <div class="flex justify-end">
      <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
    </div>
  </form>
</template>
