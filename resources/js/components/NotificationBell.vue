<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { CustomerNotification, SharedData } from '@/types'
import { router, usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage<SharedData>()
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, recent: [] })

const openNotification = (notification: CustomerNotification) => {
  const visit = () => router.visit(notification.action_url ?? route('notifications.index'))

  if (notification.read_at) {
    visit()
    return
  }

  router.patch(
    route('notifications.read', { notification: notification.id }),
    {},
    { preserveScroll: true, onSuccess: visit }
  )
}

const markAllRead = () =>
  router.patch(route('notifications.read-all'), {}, { preserveScroll: true })
</script>

<template>
  <DropdownMenu v-if="page.props.auth.user">
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="relative rounded-lg" aria-label="Notifications">
        <Bell class="size-5" />
        <span
          v-if="notifications.unread_count"
          class="absolute right-1 top-1 flex size-4 items-center justify-center rounded-full border-2 border-background bg-destructive text-[10px] font-semibold text-destructive-foreground"
          >{{ notifications.unread_count > 9 ? '9+' : notifications.unread_count }}</span
        >
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-80 rounded-xl border-border/80 p-1.5 shadow-xl">
      <div class="flex items-center justify-between px-2 py-1.5">
        <DropdownMenuLabel class="p-0">Notifications</DropdownMenuLabel>
        <button
          v-if="notifications.unread_count"
          type="button"
          class="text-xs text-primary hover:underline"
          @click="markAllRead"
        >
          Mark all as read
        </button>
      </div>
      <DropdownMenuSeparator />
      <p
        v-if="notifications.recent.length === 0"
        class="px-2 py-6 text-center text-sm text-muted-foreground"
      >
        You have no notifications yet.
      </p>
      <DropdownMenuItem
        v-for="notification in notifications.recent"
        :key="notification.id"
        class="items-start whitespace-normal py-2"
        :class="!notification.read_at && 'bg-muted/60'"
        @select="openNotification(notification)"
      >
        <div class="min-w-0 space-y-0.5">
          <p class="font-medium">{{ notification.title }}</p>
          <p class="text-xs text-muted-foreground">{{ notification.message }}</p>
        </div>
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem @select="router.visit(route('notifications.index'))"
        >View all notifications</DropdownMenuItem
      >
    </DropdownMenuContent>
  </DropdownMenu>
</template>
