<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/modules/users/components/UserForm.vue';
import type { ManagedUser } from '@/modules/users/types';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ user: ManagedUser }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: route('users.index') },
    { title: props.user.name, href: route('users.show', { user: props.user.id }) },
    { title: 'Edit', href: route('users.edit', { user: props.user.id }) },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.put(route('users.update', { user: props.user.id }));
</script>

<template>
    <Head title="Edit user" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 md:p-6">
            <Card>
                <CardHeader><CardTitle>Edit user</CardTitle></CardHeader>
                <CardContent><UserForm :form="form" submit-label="Save changes" editing @submit="submit" /></CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
