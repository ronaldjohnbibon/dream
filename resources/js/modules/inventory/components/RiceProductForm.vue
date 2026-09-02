<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { RiceProductFormData } from '@/modules/inventory/types';

interface RiceProductFormState extends RiceProductFormData {
    processing: boolean;
    errors: Partial<Record<keyof RiceProductFormData, string>>;
}

defineProps<{
    form: RiceProductFormState;
    submitLabel: string;
    editing?: boolean;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-6 sm:grid-cols-2">
            <FormField id="name" label="Name" :error="form.errors.name" required>
                <Input id="name" v-model="form.name" required autofocus />
            </FormField>

            <FormField id="brand" label="Brand" :error="form.errors.brand" required>
                <Input id="brand" v-model="form.brand" required />
            </FormField>
        </div>

        <FormField id="description" label="Description" :error="form.errors.description">
            <textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
        </FormField>

        <div class="grid gap-6 sm:grid-cols-3">
            <FormField id="sack-size" label="Sack size">
                <Input id="sack-size" model-value="25kg" disabled />
            </FormField>

            <FormField id="cost-price" label="Cost price" :error="form.errors.cost_price" required>
                <Input id="cost-price" v-model="form.cost_price" type="number" min="0" step="0.01" required />
            </FormField>

            <FormField id="selling-price" label="Selling price" :error="form.errors.selling_price" required>
                <Input id="selling-price" v-model="form.selling_price" type="number" min="0" step="0.01" required />
            </FormField>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <FormField v-if="!editing" id="initial-stock" label="Initial available stock" :error="form.errors.initial_stock" required>
                <Input id="initial-stock" v-model.number="form.initial_stock" type="number" min="0" step="1" required />
            </FormField>

            <FormField id="reorder-level" label="Reorder level" :error="form.errors.reorder_level" required>
                <Input id="reorder-level" v-model.number="form.reorder_level" type="number" min="0" step="1" required />
            </FormField>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
