<script setup lang="ts">
import { Separator } from '@/components/ui/separator'

interface Props {
  title: string
  description?: string
}

defineProps<Props>()
</script>

<template>
  <div class="mb-8 space-y-1">
    <h2 class="page-title text-xl">{{ title }}</h2>
    <p v-if="description" class="text-sm leading-6 text-muted-foreground">
      {{ description }}
    </p>
  </div>
  <Separator class="my-7" />
</template>
