<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/modules/users/components/UserForm.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: route('users.index') },
    { title: 'Create user', href: route('users.create') },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_admin: false,
});

const submit = () => form.post(route('users.store'));
</script>

<template>
    <Head title="Create user" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader><CardTitle>Create user</CardTitle></CardHeader>
                <CardContent><UserForm :form="form" submit-label="Create user" @submit="submit" /></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
