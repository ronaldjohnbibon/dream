<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { UserFormData } from '@/modules/users/types';

interface UserFormState extends UserFormData {
    processing: boolean;
    errors: Partial<Record<keyof UserFormData, string>>;
}

defineProps<{
    form: UserFormState;
    submitLabel: string;
    editing?: boolean;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <FormField id="name" label="Name" :error="form.errors.name" required>
            <Input id="name" v-model="form.name" autocomplete="name" required autofocus />
        </FormField>

        <FormField id="email" label="Email address" :error="form.errors.email" required>
            <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
        </FormField>

        <FormField id="password" :label="editing ? 'New password' : 'Password'" :error="form.errors.password" :required="!editing">
            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" :required="!editing" />
        </FormField>

        <FormField id="password_confirmation" label="Confirm password" :error="form.errors.password_confirmation" :required="!editing">
            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :required="!editing" />
        </FormField>

        <label class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm">
            <input v-model="form.is_admin" type="checkbox" class="size-4 rounded border-input text-primary focus:ring-ring" />
            <span>
                <span class="block font-medium">Administrator</span>
                <span class="block text-muted-foreground">Can manage users and view user totals.</span>
            </span>
        </label>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
