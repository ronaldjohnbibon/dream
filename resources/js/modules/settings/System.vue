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
        business_name: string;
        logo_url: string | null;
        contact_number: string | null;
        address: string | null;
        gcash_account_name: string | null;
        gcash_account_number: string | null;
        gcash_qr_code_url: string | null;
        pautang_enabled: boolean;
        pautang_installments: number;
        pautang_payment_term_days: number;
        pautang_max_active: number;
        pautang_max_sacks: number;
        pautang_grace_period_days: number;
        points_enabled: boolean;
        completed_order_points: number;
        on_time_payment_points: number;
        peso_per_point: string;
        minimum_redemption: number;
        maximum_points_usable: number;
        free_delivery_area_ids: number[];
        low_stock_threshold: number;
    };
    deliveryAreas: { id: number; name: string; delivery_fee: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System settings', href: route('system-settings.edit') }];
const form = useForm({
    business_name: props.settings.business_name,
    logo: null as File | null,
    contact_number: props.settings.contact_number ?? '',
    address: props.settings.address ?? '',
    gcash_account_name: props.settings.gcash_account_name ?? '',
    gcash_account_number: props.settings.gcash_account_number ?? '',
    gcash_qr_code: null as File | null,
    pautang_enabled: props.settings.pautang_enabled,
    pautang_installments: props.settings.pautang_installments,
    pautang_payment_term_days: props.settings.pautang_payment_term_days,
    pautang_max_active: props.settings.pautang_max_active,
    pautang_max_sacks: props.settings.pautang_max_sacks,
    pautang_grace_period_days: props.settings.pautang_grace_period_days,
    points_enabled: props.settings.points_enabled,
    completed_order_points: props.settings.completed_order_points,
    on_time_payment_points: props.settings.on_time_payment_points,
    peso_per_point: props.settings.peso_per_point,
    minimum_redemption: props.settings.minimum_redemption,
    maximum_points_usable: props.settings.maximum_points_usable,
    free_delivery_area_ids: [...props.settings.free_delivery_area_ids],
    low_stock_threshold: props.settings.low_stock_threshold,
    _method: 'put',
});

const submit = () => form.post(route('system-settings.update'), { forceFormData: true });
</script>

<template>
    <Head title="System settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader><CardTitle>General</CardTitle><CardDescription>Business details and application branding.</CardDescription></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <FormField id="business-name" label="Business name" :error="form.errors.business_name" required><Input id="business-name" v-model="form.business_name" required /></FormField>
                        <FormField id="contact-number" label="Contact number" :error="form.errors.contact_number"><Input id="contact-number" v-model="form.contact_number" /></FormField>
                        <FormField id="business-logo" label="Logo" :error="form.errors.logo" class="sm:col-span-2">
                            <img v-if="settings.logo_url" :src="settings.logo_url" alt="Business logo" class="mb-3 size-16 rounded-md border object-contain" />
                            <input id="business-logo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        </FormField>
                        <FormField id="address" label="Address" :error="form.errors.address" class="sm:col-span-2"><textarea id="address" v-model="form.address" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" /></FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>GCash</CardTitle><CardDescription>Customers can submit GCash payments only after all account details and the QR code are set.</CardDescription></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <FormField id="gcash-account-name" label="Account name" :error="form.errors.gcash_account_name"><Input id="gcash-account-name" v-model="form.gcash_account_name" /></FormField>
                        <FormField id="gcash-account-number" label="GCash number" :error="form.errors.gcash_account_number"><Input id="gcash-account-number" v-model="form.gcash_account_number" /></FormField>
                        <FormField id="gcash-qr-code" label="QR code" :error="form.errors.gcash_qr_code" class="sm:col-span-2">
                            <img v-if="settings.gcash_qr_code_url" :src="settings.gcash_qr_code_url" alt="GCash QR code" class="mb-3 max-h-48 rounded-md border object-contain" />
                            <input id="gcash-qr-code" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" @change="form.gcash_qr_code = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Pautang</CardTitle><CardDescription>These rules apply to pautang orders and schedules created after saving.</CardDescription></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-md border p-3 text-sm font-medium sm:col-span-2"><input v-model="form.pautang_enabled" type="checkbox" class="size-4" /> Enable pautang</label>
                        <FormField id="pautang-installments" label="Number of installments" :error="form.errors.pautang_installments" required><Input id="pautang-installments" v-model.number="form.pautang_installments" type="number" min="1" max="12" required /></FormField>
                        <FormField id="pautang-term" label="Payment term (days)" :error="form.errors.pautang_payment_term_days" required><Input id="pautang-term" v-model.number="form.pautang_payment_term_days" type="number" min="1" max="365" required /></FormField>
                        <FormField id="pautang-max-active" label="Maximum active pautang" :error="form.errors.pautang_max_active" required><Input id="pautang-max-active" v-model.number="form.pautang_max_active" type="number" min="1" required /></FormField>
                        <FormField id="pautang-max-sacks" label="Maximum sacks" :error="form.errors.pautang_max_sacks" required><Input id="pautang-max-sacks" v-model.number="form.pautang_max_sacks" type="number" min="1" required /></FormField>
                        <FormField id="pautang-grace" label="Grace period (days)" :error="form.errors.pautang_grace_period_days" required><Input id="pautang-grace" v-model.number="form.pautang_grace_period_days" type="number" min="0" max="30" required /></FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Points</CardTitle><CardDescription>Rewards and redemption limits apply to future customer activity.</CardDescription></CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-md border p-3 text-sm font-medium sm:col-span-2"><input v-model="form.points_enabled" type="checkbox" class="size-4" /> Enable points</label>
                        <FormField id="completed-order-points" label="Points per completed order" :error="form.errors.completed_order_points" required><Input id="completed-order-points" v-model.number="form.completed_order_points" type="number" min="0" required /></FormField>
                        <FormField id="on-time-payment-points" label="On-time payment bonus" :error="form.errors.on_time_payment_points" required><Input id="on-time-payment-points" v-model.number="form.on_time_payment_points" type="number" min="0" required /></FormField>
                        <FormField id="peso-per-point" label="Point-to-peso conversion" :error="form.errors.peso_per_point" required><Input id="peso-per-point" v-model="form.peso_per_point" type="number" min="0" step="0.01" required /></FormField>
                        <FormField id="minimum-redemption" label="Minimum redemption" :error="form.errors.minimum_redemption" required><Input id="minimum-redemption" v-model.number="form.minimum_redemption" type="number" min="1" required /></FormField>
                        <FormField id="maximum-points-usable" label="Maximum points usable per order" :error="form.errors.maximum_points_usable" required class="sm:col-span-2"><Input id="maximum-points-usable" v-model.number="form.maximum_points_usable" type="number" min="1" required /></FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Delivery</CardTitle><CardDescription>Selected active areas always receive free delivery. Other areas use their standard Delivery Areas fee.</CardDescription></CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="deliveryAreas.length === 0" class="text-sm text-muted-foreground">Add active delivery areas before selecting free-delivery coverage.</p>
                        <label v-for="area in deliveryAreas" :key="area.id" class="flex items-center justify-between gap-3 rounded-md border p-3 text-sm"><span class="flex items-center gap-3"><input v-model="form.free_delivery_area_ids" :value="area.id" type="checkbox" class="size-4" /> {{ area.name }}</span><span class="text-muted-foreground">Standard fee: ₱{{ area.delivery_fee }}</span></label>
                        <p v-if="form.errors.free_delivery_area_ids" class="text-sm text-destructive">{{ form.errors.free_delivery_area_ids }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Inventory</CardTitle><CardDescription>All products at or below this stock count are marked low stock.</CardDescription></CardHeader>
                    <CardContent><FormField id="low-stock-threshold" label="Low-stock threshold (sacks)" :error="form.errors.low_stock_threshold" required><Input id="low-stock-threshold" v-model.number="form.low_stock_threshold" type="number" min="0" required /></FormField></CardContent>
                </Card>

                <div class="flex justify-end"><Button type="submit" :disabled="form.processing">Save system settings</Button></div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
