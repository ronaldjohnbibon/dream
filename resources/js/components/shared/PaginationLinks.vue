<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

export interface PaginationLink {
  label: string
  url: string | null
  active: boolean
}

defineProps<{
  links: PaginationLink[]
}>()
</script>

<template>
  <nav v-if="links.length > 3" class="flex flex-wrap justify-end gap-1" aria-label="Pagination">
    <component
      :is="link.url ? Link : 'span'"
      v-for="link in links"
      :key="link.label"
      :href="link.url ?? undefined"
      preserve-scroll
      class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-medium transition-colors"
      :class="[
        link.active
          ? 'border-primary bg-primary text-primary-foreground shadow-sm'
          : 'bg-background hover:bg-accent',
        !link.url && 'cursor-not-allowed opacity-50',
      ]"
      v-html="link.label"
    />
  </nav>
</template>
