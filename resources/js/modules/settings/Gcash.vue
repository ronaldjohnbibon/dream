<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ qrCodeUrl: string | null }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'GCash settings', href: route('gcash-settings.edit') }];
const form = useForm({ qr_code: null as File | null });
const submit = () => form.put(route('gcash-settings.update'), { forceFormData: true });
</script>

<template>
    <Head title="GCash settings" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <Card>
                <CardHeader><CardTitle>GCash QR code</CardTitle><CardDescription>Customers see this QR code when submitting payment proof.</CardDescription></CardHeader>
                <CardContent class="space-y-6">
                    <img v-if="qrCodeUrl" :src="qrCodeUrl" alt="Configured GCash QR code" class="max-h-96 rounded-lg border object-contain" />
                    <p v-else class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">No GCash QR code is configured. Customers cannot submit GCash payments yet.</p>
                    <form class="space-y-5" @submit.prevent="submit"><FormField id="qr-code" label="QR code image" :error="form.errors.qr_code" required><input id="qr-code" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" required @change="form.qr_code = ($event.target as HTMLInputElement).files?.[0] ?? null" /></FormField><Button type="submit" :disabled="form.processing">Save QR code</Button></form>
                </CardContent>
            </Card>
        </SettingsLayout>
    </AppLayout>
</template>
