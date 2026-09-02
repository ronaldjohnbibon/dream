<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    settings: {
        completed_order_points: number;
        on_time_payment_points: number;
        peso_per_point: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Points settings', href: route('points-settings.edit') }];
const form = useForm({
    completed_order_points: props.settings.completed_order_points,
    on_time_payment_points: props.settings.on_time_payment_points,
    peso_per_point: props.settings.peso_per_point,
});

const submit = () => form.put(route('points-settings.update'));
</script>

<template>
    <Head title="Points settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <Card>
                <CardHeader>
                    <CardTitle>Points rewards</CardTitle>
                    <CardDescription>These values apply to rewards earned after the settings are saved.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-5" @submit.prevent="submit">
                        <FormField id="completed-order-points" label="Points per completed order" :error="form.errors.completed_order_points" required>
                            <Input id="completed-order-points" v-model.number="form.completed_order_points" type="number" min="0" step="1" required />
                        </FormField>
                        <FormField id="on-time-payment-points" label="Points per on-time installment" :error="form.errors.on_time_payment_points" required>
                            <Input id="on-time-payment-points" v-model.number="form.on_time_payment_points" type="number" min="0" step="1" required />
                        </FormField>
                        <FormField id="peso-per-point" label="Peso value per point" :error="form.errors.peso_per_point" required>
                            <Input id="peso-per-point" v-model="form.peso_per_point" type="number" min="0" step="0.01" required />
                        </FormField>
                        <p class="text-sm text-muted-foreground">Set a reward value to 0 to disable that automatic reward.</p>
                        <Button type="submit" :disabled="form.processing">Save points settings</Button>
                    </form>
                </CardContent>
            </Card>
        </SettingsLayout>
    </AppLayout>
</template>
