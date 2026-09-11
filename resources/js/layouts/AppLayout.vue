<script setup lang="ts">
import CustomerPushNotificationsPrompt from '@/components/CustomerPushNotificationsPrompt.vue'
import FlashNotifications from '@/components/shared/FlashNotifications.vue'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import type { BreadcrumbItemType, SharedData } from '@/types'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Props {
  breadcrumbs?: BreadcrumbItemType[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})

const page = usePage<SharedData>()
const isCustomer = computed(() => page.props.auth.user?.is_admin === false)
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <slot />
    <FlashNotifications />
    <CustomerPushNotificationsPrompt v-if="isCustomer" />
  </AppLayout>
</template>
