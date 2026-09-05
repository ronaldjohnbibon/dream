<script setup lang="ts">
import { CheckCircle2, CircleAlert, Info, TriangleAlert } from 'lucide-vue-next'
import { computed, type Component, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

type FeedbackVariant = 'success' | 'error' | 'warning' | 'info'

interface Props {
  variant: FeedbackVariant
  title?: string
  messages: string[]
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()

const config = computed(() => {
  const variants: Record<FeedbackVariant, { title: string; icon: Component; classes: string }> = {
    success: {
      title: 'Success',
      icon: CheckCircle2,
      classes:
        'border-emerald-700/30 border-l-emerald-700 bg-[hsl(var(--secondary)/0.82)] text-emerald-950 dark:border-emerald-400/35 dark:border-l-emerald-400 dark:bg-emerald-950/30 dark:text-emerald-50',
    },
    error: {
      title: 'Something went wrong',
      icon: CircleAlert,
      classes:
        'border-destructive/35 border-l-destructive bg-destructive/10 text-foreground dark:bg-destructive/15',
    },
    warning: {
      title: 'Attention needed',
      icon: TriangleAlert,
      classes:
        'border-amber-700/35 border-l-amber-700 bg-amber-50 text-amber-950 dark:border-amber-400/35 dark:border-l-amber-400 dark:bg-amber-950/30 dark:text-amber-50',
    },
    info: {
      title: 'For your information',
      icon: Info,
      classes:
        'border-primary/35 border-l-primary bg-primary/10 text-foreground dark:bg-primary/15',
    },
  }

  return variants[props.variant]
})

const announcementRole = computed(() =>
  props.variant === 'error' || props.variant === 'warning' ? 'alert' : 'status'
)
</script>

<template>
  <div
    :role="announcementRole"
    :aria-live="announcementRole === 'alert' ? 'assertive' : 'polite'"
    :class="
      cn(
        'flex items-start gap-3 border border-l-4 px-4 py-3 shadow-[0_14px_32px_-24px_hsl(var(--foreground)/0.65)]',
        config.classes,
        props.class
      )
    "
  >
    <component :is="config.icon" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold tracking-[-0.01em]">{{ title ?? config.title }}</p>
      <p v-if="messages.length === 1" class="mt-1 text-sm leading-5 opacity-85">
        {{ messages[0] }}
      </p>
      <ul v-else class="mt-1 list-disc space-y-1 pl-4 text-sm leading-5 opacity-85">
        <li v-for="message in messages" :key="message">{{ message }}</li>
      </ul>
    </div>
    <slot name="action" />
  </div>
</template>
