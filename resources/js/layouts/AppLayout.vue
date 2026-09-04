<script setup lang="ts">
import FlashNotifications from '@/components/shared/FlashNotifications.vue'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import type { BreadcrumbItemType } from '@/types'

interface Props {
  breadcrumbs?: BreadcrumbItemType[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <slot />
    <FlashNotifications />
  </AppLayout>
</template>
