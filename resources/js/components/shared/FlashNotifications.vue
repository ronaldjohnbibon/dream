<script setup lang="ts">
import type { SharedData } from '@/types'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const page = usePage<SharedData>()
const dismissedSuccess = ref<string | null>(null)
const dismissedErrors = ref<string | null>(null)
const unexpectedError = ref<string | null>(null)
const successMessage = computed(() => page.props.flash.success)
const validationErrors = computed(() => [
  ...new Set(
    Object.values(page.props.errors as Record<string, unknown>).filter(
      (message): message is string => typeof message === 'string' && message.length > 0
    )
  ),
])
const errorMessages = computed(() => [
  ...new Set([
    ...validationErrors.value,
    ...(unexpectedError.value ? [unexpectedError.value] : []),
  ]),
])
const errorKey = computed(() => errorMessages.value.join('\n'))

const removeExceptionListener = router.on('exception', () => {
  unexpectedError.value = 'We could not complete your request. Please try again.'

  return false
})

const removeSuccessListener = router.on('success', () => {
  unexpectedError.value = null
})

watch(successMessage, () => {
  dismissedSuccess.value = null
})

watch(errorKey, () => {
  dismissedErrors.value = null
})

onBeforeUnmount(() => {
  removeExceptionListener()
  removeSuccessListener()
})
</script>

<template>
  <div class="fixed right-4 top-4 z-50 w-full max-w-sm space-y-3">
    <div
      v-if="successMessage && dismissedSuccess !== successMessage"
      class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-lg"
    >
      <span>{{ successMessage }}</span>
      <button
        type="button"
        class="font-semibold"
        aria-label="Dismiss success notification"
        @click="dismissedSuccess = successMessage"
      >
        &times;
      </button>
    </div>

    <div
      v-if="errorMessages.length && dismissedErrors !== errorKey"
      role="alert"
      class="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-lg"
    >
      <ul class="min-w-0 flex-1 space-y-1">
        <li v-for="message in errorMessages" :key="message">{{ message }}</li>
      </ul>
      <button
        type="button"
        class="font-semibold"
        aria-label="Dismiss error notification"
        @click="dismissedErrors = errorKey"
      >
        &times;
      </button>
    </div>
  </div>
</template>
