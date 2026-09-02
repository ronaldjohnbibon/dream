<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{ summary: { users: number; administrators: number } | null }>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">Welcome to Business Starter.</p>
            </div>

            <div v-if="summary" class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardDescription>Total users</CardDescription>
                        <CardTitle class="text-3xl">{{ summary.users }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">All active accounts in this workspace.</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Administrators</CardDescription>
                        <CardTitle class="text-3xl">{{ summary.administrators }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">Accounts that can manage users.</CardContent>
                </Card>
            </div>

            <Card v-else>
                <CardHeader>
                    <CardTitle>Your account is ready</CardTitle>
                    <CardDescription>Use Settings to update your profile or password.</CardDescription>
                </CardHeader>
            </Card>
        </div>
    </AppLayout>
</template>
