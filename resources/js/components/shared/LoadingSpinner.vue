<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  size?: 'sm' | 'md'
  label?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
  label: 'Loading',
  class: '',
})

const sizeClass = computed(() => (props.size === 'md' ? 'size-5' : 'size-4'))
</script>

<template>
  <span class="inline-flex items-center" role="status">
    <LoaderCircle
      :class="[sizeClass, 'animate-spin motion-reduce:animate-none', props.class]"
      aria-hidden="true"
    />
    <span class="sr-only">{{ label }}</span>
  </span>
</template>
