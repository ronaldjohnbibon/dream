<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import type { SharedData } from '@/types'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Props {
  class?: string
}

defineProps<Props>()

const page = usePage<SharedData>()
const business = computed(() => page.props.business)
</script>

<template>
  <div
    class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-xl bg-sidebar-primary text-sidebar-primary-foreground shadow-[0_8px_16px_-8px_hsl(var(--sidebar-primary)/0.8)]"
  >
    <img
      v-if="business.logo_url"
      :src="business.logo_url"
      :alt="`${business.name} logo`"
      class="size-full object-contain"
    />
    <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
  </div>
  <div class="ml-2 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate font-semibold leading-none tracking-[-0.015em]">{{
      business.name
    }}</span>
  </div>
</template>
