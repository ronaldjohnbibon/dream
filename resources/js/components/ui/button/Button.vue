<script setup lang="ts">
import { cn } from '@/lib/utils'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { Primitive, type PrimitiveProps } from 'radix-vue'
import type { HTMLAttributes } from 'vue'
import { buttonVariants, type ButtonVariants } from '.'

interface Props extends PrimitiveProps {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  class?: HTMLAttributes['class']
  disabled?: boolean
  loading?: boolean
  loadingText?: string
}

const props = withDefaults(defineProps<Props>(), {
  as: 'button',
})
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class)"
    :disabled="props.disabled || props.loading"
    :aria-busy="props.loading || undefined"
  >
    <template v-if="props.loading">
      <LoadingSpinner :label="props.loadingText ?? 'Loading'" />
      {{ props.loadingText ?? 'Loading…' }}
    </template>
    <slot v-else />
  </Primitive>
</template>
