<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue'
import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogDescription,
  DialogHeader,
  DialogScrollContent,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/AppLayout.vue'
import type {
  PaginatedSystemLogs,
  SystemLog,
  SystemLogDetails,
  SystemLogFilters,
} from '@/modules/logs/types'
import type { BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{
  logs: PaginatedSystemLogs
  filters: SystemLogFilters
  types: string[]
  modules: string[]
  actions: string[]
  statuses: string[]
  users: { id: number; name: string }[]
}>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System logs', href: route('system-logs.index') }]
const filters = ref<SystemLogFilters>({ ...props.filters })
const filtering = ref(false)
const selectedLog = ref<SystemLogDetails | null>(null)
const loadingLogId = ref<number | null>(null)
const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})

const label = (value: string) =>
  value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())

const typeBadgeClass = (type: string) => {
  if (type === 'activity') return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'
  if (type === 'security') return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'

  return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200'
}

const statusBadgeClass = (status: string) => {
  if (
    [
      'success',
      'succeeded',
      'sent',
      'completed',
      'approved',
      'paid',
      'created',
      'updated',
      'deleted',
      'removed',
    ].includes(status)
  ) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
  }

  if (['failed', 'rejected', 'blocked', 'invalid'].includes(status)) {
    return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
  }

  return 'bg-muted text-muted-foreground'
}

const formatDate = (value: string) => dateFormatter.format(new Date(value))
const formatMetadata = (metadata: Record<string, unknown> | null) =>
  metadata ? JSON.stringify(metadata, null, 2) : 'No metadata.'

const applyFilters = () => {
  router.get(route('system-logs.index'), filters.value, {
    preserveState: true,
    replace: true,
    onStart: () => (filtering.value = true),
    onFinish: () => (filtering.value = false),
  })
}

const openDetails = async (log: SystemLog) => {
  loadingLogId.value = log.id

  try {
    const response = await fetch(route('system-logs.show', { systemLog: log.id }), {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) return

    const payload = (await response.json()) as { log: SystemLogDetails }
    selectedLog.value = payload.log
  } finally {
    loadingLogId.value = null
  }
}

const closeDetails = (open: boolean) => {
  if (!open) selectedLog.value = null
}
</script>

<template>
  <Head title="System logs" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="app-page">
      <div>
        <h1 class="page-title">System logs</h1>
        <p class="page-description">Review application, security, and operational events.</p>
      </div>

      <form
        class="surface-toolbar grid gap-3 md:grid-cols-2 xl:grid-cols-4"
        @submit.prevent="applyFilters"
      >
        <Input
          v-model="filters.search"
          class="xl:col-span-2"
          placeholder="Search logs, records, or users"
          aria-label="Search system logs"
        />
        <Input v-model="filters.date_from" type="date" aria-label="Start date" />
        <Input v-model="filters.date_to" type="date" aria-label="End date" />
        <select
          v-model="filters.type"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All types</option>
          <option v-for="type in types" :key="type" :value="type">{{ label(type) }}</option>
        </select>
        <select
          v-model="filters.module"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All modules</option>
          <option v-for="module in modules" :key="module" :value="module">
            {{ label(module) }}
          </option>
        </select>
        <select
          v-model="filters.action"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All actions</option>
          <option v-for="action in actions" :key="action" :value="action">
            {{ label(action) }}
          </option>
        </select>
        <select
          v-model="filters.user_id"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option :value="null">All users</option>
          <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
        </select>
        <select
          v-model="filters.status"
          class="h-9 rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="all">All statuses</option>
          <option v-for="status in statuses" :key="status" :value="status">
            {{ label(status) }}
          </option>
        </select>
        <Button type="submit" variant="outline" :loading="filtering" loading-text="Applying"
          >Apply filters</Button
        >
      </form>

      <DataTable>
        <template #head>
          <tr>
            <th class="px-4 py-3 font-medium">Date</th>
            <th class="px-4 py-3 font-medium">Type</th>
            <th class="px-4 py-3 font-medium">Action</th>
            <th class="px-4 py-3 font-medium">Module</th>
            <th class="px-4 py-3 font-medium">User</th>
            <th class="px-4 py-3 font-medium">Description</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Details</th>
          </tr>
        </template>
        <template #body>
          <tr v-if="logs.data.length === 0">
            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
              No system logs match the current filters.
            </td>
          </tr>
          <tr v-for="log in logs.data" :key="log.id">
            <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
              {{ formatDate(log.created_at) }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                :class="typeBadgeClass(log.type)"
              >
                {{ label(log.type) }}
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3">{{ label(log.action) }}</td>
            <td class="px-4 py-3">{{ log.module ? label(log.module) : '—' }}</td>
            <td class="px-4 py-3">{{ log.user?.name ?? 'System / unavailable' }}</td>
            <td class="max-w-md truncate px-4 py-3 text-muted-foreground">{{ log.description }}</td>
            <td class="px-4 py-3">
              <span
                v-if="log.status"
                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                :class="statusBadgeClass(log.status)"
              >
                {{ label(log.status) }}
              </span>
              <span v-else class="text-muted-foreground">—</span>
            </td>
            <td class="px-4 py-3 text-right">
              <Button
                size="sm"
                variant="ghost"
                :loading="loadingLogId === log.id"
                loading-text="Loading"
                @click="openDetails(log)"
                >View</Button
              >
            </td>
          </tr>
        </template>
      </DataTable>

      <div class="flex justify-end"><PaginationLinks :links="logs.links" /></div>
    </div>

    <Dialog :open="selectedLog !== null" @update:open="closeDetails">
      <DialogScrollContent class="max-w-3xl">
        <DialogHeader>
          <DialogTitle>System log details</DialogTitle>
          <DialogDescription>Read-only record #{{ selectedLog?.id }}</DialogDescription>
        </DialogHeader>

        <dl v-if="selectedLog" class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
          <div class="sm:col-span-2">
            <dt class="font-medium text-muted-foreground">Description</dt>
            <dd class="mt-1 whitespace-pre-wrap">{{ selectedLog.description }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Date and time</dt>
            <dd class="mt-1">{{ formatDate(selectedLog.created_at) }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">User</dt>
            <dd class="mt-1">{{ selectedLog.user?.name ?? 'System / unavailable' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Type</dt>
            <dd class="mt-1">{{ label(selectedLog.type) }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Status</dt>
            <dd class="mt-1">{{ selectedLog.status ? label(selectedLog.status) : '—' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Module</dt>
            <dd class="mt-1">{{ selectedLog.module ? label(selectedLog.module) : '—' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Related record ID</dt>
            <dd class="mt-1">{{ selectedLog.record_id ?? '—' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">IP address</dt>
            <dd class="mt-1">{{ selectedLog.ip_address ?? 'Unavailable' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">User agent</dt>
            <dd class="mt-1 break-all">{{ selectedLog.user_agent ?? 'Unavailable' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="font-medium text-muted-foreground">Metadata</dt>
            <dd class="mt-1">
              <pre class="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs leading-5">{{
                formatMetadata(selectedLog.metadata)
              }}</pre>
            </dd>
          </div>
        </dl>
      </DialogScrollContent>
    </Dialog>
  </AppLayout>
</template>
