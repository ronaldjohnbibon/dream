<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/AppLayout.vue'
import type { CustomerNotification, PaginatedNotifications } from '@/types'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps<{ notifications: PaginatedNotifications }>()
const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})
const formatDate = (value: string) => dateFormatter.format(new Date(value))

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

const markRead = (notification: CustomerNotification) =>
  router.patch(
    route('notifications.read', { notification: notification.id }),
    {},
    { preserveScroll: true }
  )
const markAllRead = () =>
  router.patch(route('notifications.read-all'), {}, { preserveScroll: true })
</script>

<template>
  <Head title="Notifications" />

  <AppLayout :breadcrumbs="[{ title: 'Notifications', href: route('notifications.index') }]">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Order, payment, installment, and points updates.
          </p>
        </div>
        <Button class="w-full sm:w-auto" variant="outline" @click="markAllRead"
          >Mark all as read</Button
        >
      </div>

      <Card>
        <CardHeader
          ><CardTitle>Notification history</CardTitle
          ><CardDescription
            >Notifications are kept here for your reference.</CardDescription
          ></CardHeader
        >
        <CardContent class="space-y-4">
          <div class="space-y-3 md:hidden">
            <p
              v-if="notifications.data.length === 0"
              class="py-8 text-center text-sm text-muted-foreground"
            >
              You have no notifications yet.
            </p>
            <article
              v-for="notification in notifications.data"
              :key="notification.id"
              class="rounded-lg border p-4"
              :class="!notification.read_at && 'bg-muted/40'"
            >
              <p class="font-medium">{{ notification.title }}</p>
              <p class="mt-1 text-sm text-muted-foreground">{{ notification.message }}</p>
              <div class="mt-3 flex items-center justify-between gap-3">
                <p class="text-xs text-muted-foreground">
                  {{ formatDate(notification.created_at) }}
                </p>
                <Button size="sm" variant="outline" @click="openNotification(notification)"
                  >View</Button
                >
              </div>
              <Button
                v-if="!notification.read_at"
                class="mt-2"
                size="sm"
                variant="ghost"
                @click="markRead(notification)"
                >Mark read</Button
              >
            </article>
          </div>
          <div class="hidden md:block">
            <DataTable>
              <template #head
                ><tr>
                  <th class="px-4 py-3 font-medium">Notification</th>
                  <th class="px-4 py-3 font-medium">Received</th>
                  <th class="px-4 py-3 text-right font-medium">Action</th>
                </tr></template
              >
              <template #body>
                <tr v-if="notifications.data.length === 0">
                  <td colspan="3" class="px-4 py-10 text-center text-muted-foreground">
                    You have no notifications yet.
                  </td>
                </tr>
                <tr
                  v-for="notification in notifications.data"
                  :key="notification.id"
                  :class="!notification.read_at && 'bg-muted/40'"
                >
                  <td class="px-4 py-3">
                    <p class="font-medium">{{ notification.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ notification.message }}</p>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 text-sm text-muted-foreground">
                    {{ formatDate(notification.created_at) }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex gap-2">
                      <Button size="sm" variant="outline" @click="openNotification(notification)"
                        >View</Button
                      ><Button
                        v-if="!notification.read_at"
                        size="sm"
                        variant="ghost"
                        @click="markRead(notification)"
                        >Mark read</Button
                      >
                    </div>
                  </td>
                </tr>
              </template>
            </DataTable>
          </div>
          <div class="flex justify-end"><PaginationLinks :links="notifications.links" /></div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
