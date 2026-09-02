<script setup lang="ts">
import ConfirmModal from '@/components/shared/ConfirmModal.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PaginationLinks from '@/components/shared/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ManagedUser, PaginatedUsers, UserFilters } from '@/modules/users/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    users: PaginatedUsers;
    filters: UserFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: route('users.index') }];
const filters = ref<UserFilters>({ ...props.filters });
const selectedUser = ref<ManagedUser | null>(null);
const deleting = ref(false);
const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

const deleteDescription = computed(() => selectedUser.value ? `This permanently deletes ${selectedUser.value.name}. This action cannot be undone.` : '');

const applyFilters = () => {
    router.get(route('users.index'), filters.value, { preserveState: true, replace: true });
};

const sortBy = (sort: UserFilters['sort']) => {
    filters.value.sort = sort;
    filters.value.direction = props.filters.sort === sort && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyFilters();
};

const sortIndicator = (sort: UserFilters['sort']) => props.filters.sort === sort ? (props.filters.direction === 'asc' ? ' ↑' : ' ↓') : '';

const deleteUser = () => {
    if (!selectedUser.value) return;

    deleting.value = true;
    router.delete(route('users.destroy', { user: selectedUser.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
        },
        onSuccess: () => {
            selectedUser.value = null;
        },
    });
};
</script>

<template>
    <Head title="Administrators" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Administrators</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage administrator accounts.</p>
                </div>
                <Button as-child><Link :href="route('users.create')">Create administrator</Link></Button>
            </div>

            <form class="grid gap-3 rounded-lg border bg-card p-4 md:grid-cols-[1fr_auto]" @submit.prevent="applyFilters">
                <Input v-model="filters.search" placeholder="Search name or email" aria-label="Search administrators" />
                <Button type="submit" variant="outline">Apply filters</Button>
            </form>

            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 font-medium"><button type="button" @click="sortBy('name')">Name{{ sortIndicator('name') }}</button></th>
                        <th class="px-4 py-3 font-medium"><button type="button" @click="sortBy('email')">Email{{ sortIndicator('email') }}</button></th>
                        <th class="px-4 py-3 font-medium"><button type="button" @click="sortBy('created_at')">Created{{ sortIndicator('created_at') }}</button></th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </template>
                <template #body>
                    <tr v-if="users.data.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No administrators match the current filters.</td>
                    </tr>
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ user.email }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ dateFormatter.format(new Date(user.created_at)) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="ghost" size="sm" as-child><Link :href="route('users.show', { user: user.id })">View</Link></Button>
                                <Button variant="ghost" size="sm" as-child><Link :href="route('users.edit', { user: user.id })">Edit</Link></Button>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="selectedUser = user">Delete</Button>
                            </div>
                        </td>
                    </tr>
                </template>
            </DataTable>

            <div class="flex justify-end text-sm text-muted-foreground">
                <PaginationLinks :links="users.links" />
            </div>
        </div>

        <ConfirmModal
            :open="selectedUser !== null"
            title="Delete user"
            :description="deleteDescription"
            confirm-label="Delete user"
            :processing="deleting"
            @confirm="deleteUser"
            @close="selectedUser = null"
        />
    </AppLayout>
</template>
