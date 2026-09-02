<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

interface Props {
    id: string;
    label: string;
    error?: string;
    required?: boolean;
}

withDefaults(defineProps<Props>(), {
    error: undefined,
    required: false,
});
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}<span v-if="required" class="text-destructive"> *</span></Label>
        <slot />
        <InputError :message="error" />
    </div>
</template>
