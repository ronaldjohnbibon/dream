<script setup lang="ts">
import { cn } from '@/lib/utils'
import type { HTMLAttributes } from 'vue'

interface SkeletonProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<SkeletonProps>()
</script>

<template>
  <div
    :class="
      cn('feedback-shimmer relative overflow-hidden rounded-none bg-secondary/80', props.class)
    "
  />
</template>
