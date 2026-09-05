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
  <div class="fixed right-4 top-4 z-50 w-[calc(100%-2rem)] max-w-sm space-y-3">
    <div
      v-if="successMessage && dismissedSuccess !== successMessage"
      class="flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-[0_16px_40px_-20px_rgba(6,78,59,0.45)] dark:border-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-100"
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
      class="flex items-start gap-3 rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive shadow-[0_16px_40px_-20px_rgba(127,29,29,0.4)]"
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
