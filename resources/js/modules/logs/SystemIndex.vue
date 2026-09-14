<script setup lang="ts">
/* global route */

import PaginationLinks from '@/components/shared/PaginationLinks.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
  SystemLogAttentionItem,
  SystemLogDetails,
  SystemLogFilters,
  SystemLogsDashboard,
} from '@/modules/logs/types'
import type { BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import {
  Activity,
  AlertCircle,
  AlertTriangle,
  Award,
  BellRing,
  CheckCircle2,
  Clock3,
  CreditCard,
  ShieldAlert,
  ShieldCheck,
  ShoppingCart,
  TriangleAlert,
} from 'lucide-vue-next'
import { computed, ref, type Component } from 'vue'

const props = defineProps<{
  logs: PaginatedSystemLogs
  filters: SystemLogFilters
  types: string[]
  modules: string[]
  statuses: string[]
  users: { id: number; name: string }[]
  dashboard: SystemLogsDashboard
}>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System logs', href: route('system-logs.index') }]
const filters = ref<SystemLogFilters>({ ...props.filters })
const filtering = ref(false)
const selectedLog = ref<SystemLogDetails | null>(null)
const loadingLogId = ref<number | null>(null)
const errorStatuses = ['failed', 'rejected', 'blocked', 'invalid']
const warningStatuses = ['started', 'pending', 'pending_verification', 'processing']
const successStatuses = [
  'success',
  'succeeded',
  'sent',
  'completed',
  'approved',
  'paid',
  'created',
  'updated',
]
const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})
const dayFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: 'Asia/Manila',
  month: 'short',
  day: 'numeric',
  year: 'numeric',
})
const dateKeyFormatter = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Asia/Manila',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})
const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

const quickFilters = [
  { label: 'All', patch: { type: 'all', severity: 'all', category: 'all' } },
  { label: 'Errors', patch: { type: 'all', severity: 'error', category: 'all' } },
  { label: 'Warnings', patch: { type: 'all', severity: 'warning', category: 'all' } },
  { label: 'Security', patch: { type: 'security', severity: 'all', category: 'all' } },
  { label: 'Payments', patch: { type: 'all', severity: 'all', category: 'payments' } },
  { label: 'Orders', patch: { type: 'all', severity: 'all', category: 'orders' } },
  { label: 'Points', patch: { type: 'all', severity: 'all', category: 'points' } },
  { label: 'Notifications', patch: { type: 'all', severity: 'all', category: 'notifications' } },
] as const

const label = (value: string) =>
  value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())

const eventTone = (log: SystemLog) => {
  if (log.type === 'security') return 'security'
  if (log.status && errorStatuses.includes(log.status)) return 'error'
  if (log.status && warningStatuses.includes(log.status)) return 'warning'
  if (log.status && successStatuses.includes(log.status)) return 'success'

  return 'info'
}

const eventIcon = (log: SystemLog): Component => {
  if (eventTone(log) === 'error') return AlertCircle
  if (log.type === 'security') return ShieldAlert
  if (log.action.includes('payment')) return CreditCard
  if (log.action.includes('point')) return Award
  if (log.action.includes('push') || log.module === 'notifications') return BellRing
  if (log.action.includes('reminder')) return Clock3
  if (log.module === 'orders' || log.module === 'pautang') return ShoppingCart

  return Activity
}

const toneIconClass = (tone: string) => {
  if (tone === 'error') return 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
  if (tone === 'warning') return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
  if (tone === 'success')
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
  if (tone === 'security')
    return 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300'

  return 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'
}

const toneBorderClass = (tone: string) => {
  if (tone === 'error') return 'border-rose-200/80 hover:border-rose-300 dark:border-rose-900'
  if (tone === 'warning') return 'border-amber-200/80 hover:border-amber-300 dark:border-amber-900'
  if (tone === 'security')
    return 'border-violet-200/80 hover:border-violet-300 dark:border-violet-900'

  return 'border-border/80 hover:border-primary/30'
}

const statusBadgeClass = (status: string | null) => {
  if (status && errorStatuses.includes(status))
    return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
  if (status && warningStatuses.includes(status))
    return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
  if (status && successStatuses.includes(status))
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'

  return 'bg-muted text-muted-foreground'
}

const typeBadgeClass = (type: string) => {
  if (type === 'security')
    return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200'
  if (type === 'activity') return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'

  return 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200'
}

const formatDate = (value: string) => dateFormatter.format(new Date(value))
const manilaDateKey = (value: Date) => dateKeyFormatter.format(value)

const relativeTime = (value: string) => {
  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)

  if (Math.abs(seconds) < 60) return relativeFormatter.format(seconds, 'second')
  if (Math.abs(seconds) < 3600) return relativeFormatter.format(Math.round(seconds / 60), 'minute')
  if (Math.abs(seconds) < 86400) return relativeFormatter.format(Math.round(seconds / 3600), 'hour')

  return relativeFormatter.format(Math.round(seconds / 86400), 'day')
}

const timelineGroups = computed(() => {
  const today = manilaDateKey(new Date())
  const yesterday = manilaDateKey(new Date(Date.now() - 86_400_000))
  const groups = new Map<string, { label: string; logs: SystemLog[] }>()

  for (const log of props.logs.data) {
    const logDate = new Date(log.created_at)
    const key = manilaDateKey(logDate)
    const group = groups.get(key) ?? {
      label:
        key === today ? 'Today' : key === yesterday ? 'Yesterday' : dayFormatter.format(logDate),
      logs: [],
    }
    group.logs.push(log)
    groups.set(key, group)
  }

  return [...groups.entries()].map(([key, group]) => ({ key, ...group }))
})

const summaryCards = computed(() => [
  {
    label: 'Total events',
    value: props.dashboard.summary.total_events,
    icon: Activity,
    tone: 'info',
  },
  { label: 'Errors', value: props.dashboard.summary.errors, icon: AlertCircle, tone: 'error' },
  {
    label: 'Warnings',
    value: props.dashboard.summary.warnings,
    icon: TriangleAlert,
    tone: 'warning',
  },
  {
    label: 'Security events',
    value: props.dashboard.summary.security_events,
    icon: ShieldCheck,
    tone: 'security',
  },
  {
    label: 'Failed notifications',
    value: props.dashboard.summary.failed_notifications,
    icon: BellRing,
    tone: 'error',
  },
  {
    label: 'Successful notifications',
    value: props.dashboard.summary.successful_notifications,
    icon: CheckCircle2,
    tone: 'success',
  },
])

const metadataEntries = computed(() => {
  if (!selectedLog.value?.metadata) return []

  return Object.entries(selectedLog.value.metadata).map(([key, value]) => ({
    key,
    label: label(key),
    value: typeof value === 'string' ? value : JSON.stringify(value, null, 2),
  }))
})

const errorMetadata = computed(() =>
  metadataEntries.value.filter((entry) => /error|exception|failure/i.test(entry.key))
)

const applyFilters = () => {
  router.get(route('system-logs.index'), filters.value, {
    preserveState: true,
    replace: true,
    onStart: () => (filtering.value = true),
    onFinish: () => (filtering.value = false),
  })
}

const setQuickFilter = (patch: Partial<SystemLogFilters>) => {
  filters.value = { ...filters.value, action: 'all', ...patch }
  applyFilters()
}

const isQuickFilterActive = (patch: Partial<SystemLogFilters>) =>
  Object.entries(patch).every(
    ([key, value]) => filters.value[key as keyof SystemLogFilters] === value
  )

const clearFilters = () => {
  filters.value = {
    search: '',
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    type: 'all',
    module: 'all',
    action: 'all',
    user_id: null,
    status: 'all',
    severity: 'all',
    category: 'all',
  }
  applyFilters()
}

const openIssue = (issue: SystemLogAttentionItem) => {
  filters.value = {
    ...filters.value,
    type: 'all',
    category: 'all',
    severity: 'error',
    module: issue.module ?? 'all',
    action: issue.action,
  }
  applyFilters()
}

const healthFilter = (key: string) => {
  if (key === 'scheduler')
    return setQuickFilter({ category: 'scheduler', severity: 'all', type: 'all' })
  if (key === 'security')
    return setQuickFilter({ type: 'security', category: 'all', severity: 'all' })

  setQuickFilter({ category: 'notifications', severity: 'all', type: 'all' })
}

const healthStateLabel = (state: string) => {
  if (state === 'attention') return 'Attention required'
  if (state === 'recent_success') return 'Recent success'

  return 'No recent log data'
}

const healthStateClass = (state: string) => {
  if (state === 'attention') return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
  if (state === 'recent_success')
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'

  return 'bg-muted text-muted-foreground'
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
  <Head title="System monitoring" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="app-page">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h1 class="page-title">System monitoring</h1>
          <p class="page-description">
            Review system activity, failures, security events, and background work in one place.
          </p>
        </div>
        <Button variant="outline" @click="clearFilters">Reset dashboard</Button>
      </div>

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="System summary">
        <Card v-for="card in summaryCards" :key="card.label" class="metric-card">
          <CardContent class="flex items-center gap-4 p-5 sm:p-6">
            <span
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
              :class="toneIconClass(card.tone)"
            >
              <component :is="card.icon" class="h-5 w-5" />
            </span>
            <div>
              <p class="text-sm font-medium text-muted-foreground">{{ card.label }}</p>
              <p class="mt-1 text-2xl font-semibold tracking-tight">
                {{ card.value.toLocaleString() }}
              </p>
            </div>
          </CardContent>
        </Card>
      </section>

      <Card
        v-if="dashboard.attention.length"
        class="border-rose-200 bg-rose-50/50 dark:border-rose-950 dark:bg-rose-950/20"
      >
        <CardHeader class="flex-row items-start gap-3 space-y-0">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
          >
            <AlertTriangle class="h-5 w-5" />
          </span>
          <div>
            <CardTitle>Attention required</CardTitle>
            <CardDescription
              >Recent failures that match the active dashboard filters.</CardDescription
            >
          </div>
        </CardHeader>
        <CardContent class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
          <button
            v-for="issue in dashboard.attention"
            :key="`${issue.module}-${issue.action}`"
            class="rounded-lg border border-rose-200 bg-card p-3 text-left transition-colors hover:bg-rose-100/60 dark:border-rose-900 dark:hover:bg-rose-950/60"
            @click="openIssue(issue)"
          >
            <div class="flex items-start justify-between gap-3">
              <p class="font-medium">{{ label(issue.action) }}</p>
              <span
                class="status-pill bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                >{{ issue.count }}</span
              >
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ issue.module ? label(issue.module) : 'System' }} ·
              {{ relativeTime(issue.latest_at) }}
            </p>
          </button>
        </CardContent>
      </Card>

      <section class="surface-toolbar space-y-4" aria-label="System log filters">
        <div class="flex flex-wrap gap-2">
          <button
            v-for="quickFilter in quickFilters"
            :key="quickFilter.label"
            type="button"
            class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
            :class="
              isQuickFilterActive(quickFilter.patch)
                ? 'border-primary bg-primary text-primary-foreground'
                : 'bg-background text-muted-foreground hover:bg-accent'
            "
            @click="setQuickFilter(quickFilter.patch)"
          >
            {{ quickFilter.label }}
          </button>
        </div>

        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
          <Input
            v-model="filters.search"
            class="xl:col-span-2"
            placeholder="Search events, records, or users"
            aria-label="Search system logs"
          />
          <Input v-model="filters.date_from" type="date" aria-label="Start date" />
          <Input v-model="filters.date_to" type="date" aria-label="End date" />
          <select v-model="filters.user_id" aria-label="Filter by user">
            <option :value="null">All users</option>
            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
          </select>
          <select v-model="filters.module" aria-label="Filter by module">
            <option value="all">All modules</option>
            <option v-for="module in modules" :key="module" :value="module">
              {{ label(module) }}
            </option>
          </select>
          <select v-model="filters.status" aria-label="Filter by status">
            <option value="all">All statuses</option>
            <option v-for="status in statuses" :key="status" :value="status">
              {{ label(status) }}
            </option>
          </select>
          <Button type="submit" variant="outline" :loading="filtering" loading-text="Applying"
            >Apply filters</Button
          >
        </form>
      </section>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.8fr)]">
        <Card>
          <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
            <div>
              <CardTitle>Recent system activity</CardTitle>
              <CardDescription
                >Newest matching events, grouped by Manila calendar day.</CardDescription
              >
            </div>
            <span class="status-pill bg-muted text-muted-foreground"
              >{{ logs.data.length }} shown</span
            >
          </CardHeader>
          <CardContent>
            <p
              v-if="logs.data.length === 0"
              class="py-12 text-center text-sm text-muted-foreground"
            >
              No system events match the current dashboard filters.
            </p>
            <div v-else class="space-y-7">
              <section v-for="group in timelineGroups" :key="group.key">
                <p
                  class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground"
                >
                  {{ group.label }}
                </p>
                <div class="relative space-y-3 border-l border-border pl-5 sm:pl-6">
                  <button
                    v-for="log in group.logs"
                    :key="log.id"
                    class="relative block w-full rounded-xl border bg-card p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                    :class="toneBorderClass(eventTone(log))"
                    @click="openDetails(log)"
                  >
                    <span
                      class="absolute -left-[2.05rem] top-5 flex h-6 w-6 items-center justify-center rounded-full border-4 border-card sm:-left-[2.3rem]"
                      :class="toneIconClass(eventTone(log))"
                    >
                      <component :is="eventIcon(log)" class="h-3.5 w-3.5" />
                    </span>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="font-semibold">{{ label(log.action) }}</p>
                          <span class="status-pill" :class="typeBadgeClass(log.type)">{{
                            label(log.type)
                          }}</span>
                          <span
                            v-if="log.status"
                            class="status-pill"
                            :class="statusBadgeClass(log.status)"
                            >{{ label(log.status) }}</span
                          >
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                          {{ log.description }}
                        </p>
                        <p class="mt-3 text-xs text-muted-foreground">
                          {{ log.module ? label(log.module) : 'System' }}
                          <span v-if="log.user"> · {{ log.user.name }}</span>
                        </p>
                      </div>
                      <time
                        class="shrink-0 text-sm text-muted-foreground"
                        :datetime="log.created_at"
                        >{{ relativeTime(log.created_at) }}</time
                      >
                    </div>
                  </button>
                </div>
              </section>
            </div>
          </CardContent>
        </Card>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>System health</CardTitle>
              <CardDescription>Signals derived only from matching system logs.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <button
                v-for="signal in dashboard.health"
                :key="signal.key"
                class="w-full rounded-xl border p-4 text-left transition-colors hover:bg-muted/50"
                @click="healthFilter(signal.key)"
              >
                <div class="flex items-start justify-between gap-3">
                  <p class="font-medium">{{ signal.label }}</p>
                  <span class="status-pill" :class="healthStateClass(signal.state)">{{
                    healthStateLabel(signal.state)
                  }}</span>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                  <template v-if="signal.state === 'attention'"
                    >{{ signal.count }} logged failure{{ signal.count === 1 ? '' : 's' }} in this
                    view.</template
                  >
                  <template v-else-if="signal.latest_at"
                    >Last matching success {{ relativeTime(signal.latest_at) }}.</template
                  >
                  <template v-else>No matching log data is available.</template>
                </p>
              </button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activity categories</CardTitle>
              <CardDescription>Recent events organized by operational area.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <p
                v-if="dashboard.categories.length === 0"
                class="py-4 text-sm text-muted-foreground"
              >
                No category activity matches the current filters.
              </p>
              <button
                v-for="category in dashboard.categories"
                :key="category.key"
                class="block w-full rounded-xl border p-4 text-left transition-colors hover:bg-muted/50"
                @click="setQuickFilter({ category: category.key, severity: 'all', type: 'all' })"
              >
                <div class="flex items-center justify-between gap-3">
                  <p class="font-medium">{{ category.label }}</p>
                  <span class="text-xs text-muted-foreground"
                    >{{ category.logs.length }} recent</span
                  >
                </div>
                <div class="mt-3 space-y-2">
                  <div
                    v-for="log in category.logs"
                    :key="log.id"
                    class="flex items-start gap-2 text-sm"
                  >
                    <component
                      :is="eventIcon(log)"
                      class="mt-0.5 h-4 w-4 shrink-0"
                      :class="toneIconClass(eventTone(log)).split(' ')[1]"
                    />
                    <span class="min-w-0"
                      ><span class="font-medium">{{ label(log.action) }}</span
                      ><span class="text-muted-foreground">
                        · {{ relativeTime(log.created_at) }}</span
                      ></span
                    >
                  </div>
                </div>
              </button>
            </CardContent>
          </Card>
        </div>
      </div>

      <div class="flex justify-end"><PaginationLinks :links="logs.links" /></div>
    </div>

    <Dialog :open="selectedLog !== null" @update:open="closeDetails">
      <DialogScrollContent class="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{{
            selectedLog ? label(selectedLog.action) : 'System event details'
          }}</DialogTitle>
          <DialogDescription>Read-only event #{{ selectedLog?.id }}</DialogDescription>
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
            <dd class="mt-1">
              {{ selectedLog.status ? label(selectedLog.status) : 'Unavailable' }}
            </dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Module</dt>
            <dd class="mt-1">{{ selectedLog.module ? label(selectedLog.module) : 'System' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">Related record ID</dt>
            <dd class="mt-1">{{ selectedLog.record_id ?? 'Unavailable' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">IP address</dt>
            <dd class="mt-1">{{ selectedLog.ip_address ?? 'Unavailable' }}</dd>
          </div>
          <div>
            <dt class="font-medium text-muted-foreground">User agent</dt>
            <dd class="mt-1 break-all">{{ selectedLog.user_agent ?? 'Unavailable' }}</dd>
          </div>
          <div v-if="errorMetadata.length" class="sm:col-span-2">
            <dt class="font-medium text-muted-foreground">Error information</dt>
            <dd class="mt-2 grid gap-2 rounded-lg border bg-muted/30 p-3">
              <div v-for="entry in errorMetadata" :key="entry.key">
                <span class="font-medium">{{ entry.label }}:</span>
                <span class="whitespace-pre-wrap">{{ entry.value }}</span>
              </div>
            </dd>
          </div>
          <div v-if="metadataEntries.length" class="sm:col-span-2">
            <dt class="font-medium text-muted-foreground">Metadata</dt>
            <dd class="mt-2 grid gap-2 rounded-lg border bg-muted/30 p-3">
              <div
                v-for="entry in metadataEntries"
                :key="entry.key"
                class="grid gap-1 sm:grid-cols-[10rem_1fr]"
              >
                <span class="font-medium">{{ entry.label }}</span
                ><span class="whitespace-pre-wrap break-words">{{ entry.value }}</span>
              </div>
            </dd>
          </div>
        </dl>
      </DialogScrollContent>
    </Dialog>
  </AppLayout>
</template>
