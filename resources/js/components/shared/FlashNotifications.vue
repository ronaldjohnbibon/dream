<script setup lang="ts">
import FeedbackAlert from '@/components/shared/FeedbackAlert.vue'
import { X } from 'lucide-vue-next'
import type { SharedData } from '@/types'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

type NotificationVariant = 'success' | 'error' | 'warning' | 'info'

interface NotificationItem {
  id: string
  variant: NotificationVariant
  messages: string[]
  title?: string
  autoDismiss?: boolean
}

const page = usePage<SharedData>()
const dismissed = ref(new Set<string>())
const unexpectedError = ref<string | null>(null)
const dismissalTimers = new Map<string, ReturnType<typeof setTimeout>>()

const validationErrors = computed(() => [
  ...new Set(
    Object.values(page.props.errors as Record<string, unknown>).filter(
      (message): message is string => typeof message === 'string' && message.length > 0
    )
  ),
])

const sourceNotifications = computed<NotificationItem[]>(() => {
  const items: NotificationItem[] = []
  const flashItems: Array<{
    variant: Exclude<NotificationVariant, 'error'>
    message: string | null
  }> = [
    { variant: 'success', message: page.props.flash.success },
    { variant: 'warning', message: page.props.flash.warning },
    { variant: 'info', message: page.props.flash.info },
  ]

  for (const { variant, message } of flashItems) {
    if (message) {
      items.push({
        id: `${variant}:${message}`,
        variant,
        messages: [message],
        autoDismiss: variant === 'success' || variant === 'info',
      })
    }
  }

  const errors = [
    ...validationErrors.value,
    ...(unexpectedError.value ? [unexpectedError.value] : []),
  ]
  if (errors.length) {
    items.push({
      id: `error:${errors.join('\n')}`,
      variant: 'error',
      messages: [...new Set(errors)],
    })
  }

  return items
})

const notifications = computed(() =>
  sourceNotifications.value.filter((item) => !dismissed.value.has(item.id))
)

const dismiss = (id: string) => {
  dismissed.value = new Set([...dismissed.value, id])
  const timer = dismissalTimers.get(id)
  if (timer) clearTimeout(timer)
  dismissalTimers.delete(id)
}

const removeExceptionListener = router.on('exception', () => {
  unexpectedError.value = 'We could not complete your request. Please try again.'
  return false
})

const removeSuccessListener = router.on('success', () => {
  unexpectedError.value = null
  dismissed.value = new Set()
})

watch(
  sourceNotifications,
  (currentNotifications) => {
    const currentIds = new Set(currentNotifications.map((item) => item.id))
    dismissed.value = new Set([...dismissed.value].filter((id) => currentIds.has(id)))

    for (const [id, timer] of dismissalTimers) {
      if (!currentIds.has(id)) {
        clearTimeout(timer)
        dismissalTimers.delete(id)
      }
    }

    for (const item of currentNotifications) {
      if (item.autoDismiss && !dismissalTimers.has(item.id)) {
        dismissalTimers.set(
          item.id,
          setTimeout(() => dismiss(item.id), 5000)
        )
      }
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  removeExceptionListener()
  removeSuccessListener()
  dismissalTimers.forEach((timer) => clearTimeout(timer))
})
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-4 top-4 z-50 mx-auto w-auto max-w-md sm:left-auto sm:right-6 sm:mx-0"
  >
    <TransitionGroup
      name="feedback-notification"
      tag="div"
      class="flex flex-col gap-3"
      enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
      enter-from-class="translate-y-[-0.5rem] opacity-0 sm:translate-x-3 sm:translate-y-0"
      enter-to-class="translate-x-0 translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
      leave-from-class="translate-x-0 translate-y-0 opacity-100"
      leave-to-class="translate-y-[-0.5rem] opacity-0 sm:translate-x-3 sm:translate-y-0"
    >
      <FeedbackAlert
        v-for="notification in notifications"
        :key="notification.id"
        :variant="notification.variant"
        :title="notification.title"
        :messages="notification.messages"
        class="pointer-events-auto w-full"
      >
        <template #action>
          <button
            type="button"
            class="-mr-1 -mt-1 inline-flex size-7 shrink-0 items-center justify-center rounded-none text-current/70 transition-colors hover:bg-black/5 hover:text-current focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:hover:bg-white/10"
            :aria-label="`Dismiss ${notification.variant} notification`"
            @click="dismiss(notification.id)"
          >
            <X class="size-4" aria-hidden="true" />
          </button>
        </template>
      </FeedbackAlert>
    </TransitionGroup>
  </div>
</template>
