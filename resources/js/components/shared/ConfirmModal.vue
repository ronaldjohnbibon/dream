<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Props {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    processing?: boolean;
}

withDefaults(defineProps<Props>(), {
    confirmLabel: 'Confirm',
    processing: false,
});

const emit = defineEmits<{
    confirm: [];
    close: [];
}>();

const handleOpenChange = (open: boolean) => {
    if (!open) {
        emit('close');
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" type="button" :disabled="processing" @click="emit('close')">Cancel</Button>
                <Button variant="destructive" type="button" :disabled="processing" @click="emit('confirm')">{{ confirmLabel }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
