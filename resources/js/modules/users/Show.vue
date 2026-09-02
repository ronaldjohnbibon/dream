<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ManagedUser } from '@/modules/users/types';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{ user: ManagedUser }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: route('users.index') },
    { title: props.user.name, href: route('users.show', { user: props.user.id }) },
];

const formattedDate = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ user.name }}</h1>
                    <p class="text-sm text-muted-foreground">User details</p>
                </div>
                <Button as-child><Link :href="route('users.edit', { user: user.id })">Edit user</Link></Button>
            </div>

            <Card>
                <CardHeader><CardTitle>Account information</CardTitle></CardHeader>
                <CardContent class="grid gap-5 text-sm sm:grid-cols-2">
                    <div><p class="text-muted-foreground">Email</p><p class="mt-1 font-medium">{{ user.email }}</p></div>
                    <div><p class="text-muted-foreground">Account type</p><p class="mt-1 font-medium">{{ user.is_admin ? 'Administrator' : 'User' }}</p></div>
                    <div><p class="text-muted-foreground">Created</p><p class="mt-1 font-medium">{{ formattedDate.format(new Date(user.created_at)) }}</p></div>
                    <div><p class="text-muted-foreground">Last updated</p><p class="mt-1 font-medium">{{ formattedDate.format(new Date(user.updated_at)) }}</p></div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
