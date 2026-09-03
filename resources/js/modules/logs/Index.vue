<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import PaginationLinks from '@/components/shared/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityLogFilters, PaginatedActivityLogs } from '@/modules/logs/types';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    logs: PaginatedActivityLogs;
    filters: ActivityLogFilters;
    modules: string[];
    actions: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Logs', href: route('activity-logs.index') }];
const filters = ref<ActivityLogFilters>({ ...props.filters });
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());

const applyFilters = () => {
    router.get(route('activity-logs.index'), filters.value, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Activity logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Activity logs</h1>
                <p class="mt-1 text-sm text-muted-foreground">Track important operational actions across the business.</p>
            </div>

            <form class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_180px_180px_auto]" @submit.prevent="applyFilters">
                <Input v-model="filters.search" placeholder="Search descriptions or users" aria-label="Search activity logs" />
                <select v-model="filters.module" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="all">All modules</option>
                    <option v-for="module in modules" :key="module" :value="module">{{ label(module) }}</option>
                </select>
                <select v-model="filters.action" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option value="all">All actions</option>
                    <option v-for="action in actions" :key="action" :value="action">{{ label(action) }}</option>
                </select>
                <Button type="submit" variant="outline">Apply filters</Button>
            </form>

            <DataTable>
                <template #head><tr><th class="px-4 py-3 font-medium">Date</th><th class="px-4 py-3 font-medium">User</th><th class="px-4 py-3 font-medium">Module</th><th class="px-4 py-3 font-medium">Action</th><th class="px-4 py-3 font-medium">Related record</th><th class="px-4 py-3 font-medium">Description</th></tr></template>
                <template #body>
                    <tr v-if="logs.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No activity logs match the current filters.</td></tr>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ dateFormatter.format(new Date(log.created_at)) }}</td>
                        <td class="px-4 py-3">{{ log.user_name }}</td>
                        <td class="px-4 py-3">{{ label(log.module) }}</td>
                        <td class="px-4 py-3">{{ label(log.action) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ log.related_record }}</td>
                        <td class="max-w-xl px-4 py-3 text-muted-foreground">{{ log.description }}</td>
                    </tr>
                </template>
            </DataTable>

            <div class="flex justify-end"><PaginationLinks :links="logs.links" /></div>
        </div>
    </AppLayout>
</template>
