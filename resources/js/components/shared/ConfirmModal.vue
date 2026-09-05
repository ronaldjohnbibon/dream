<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface Props {
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  processing?: boolean
}

withDefaults(defineProps<Props>(), {
  confirmLabel: 'Confirm',
  processing: false,
})

const emit = defineEmits<{
  confirm: []
  close: []
}>()

const handleOpenChange = (open: boolean) => {
  if (!open) {
    emit('close')
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent class="rounded-2xl border-border/80 p-0 shadow-2xl">
      <DialogHeader>
        <div class="rounded-t-2xl bg-destructive/10 px-6 py-5">
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription class="mt-1.5">{{ description }}</DialogDescription>
        </div>
      </DialogHeader>
      <DialogFooter class="px-6 pb-6">
        <Button variant="outline" type="button" :disabled="processing" @click="emit('close')"
          >Cancel</Button
        >
        <Button
          variant="destructive"
          type="button"
          :disabled="processing"
          @click="emit('confirm')"
          >{{ confirmLabel }}</Button
        >
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
