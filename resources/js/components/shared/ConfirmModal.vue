<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { OctagonAlert, TriangleAlert } from 'lucide-vue-next'
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
  variant?: 'destructive' | 'warning'
}

withDefaults(defineProps<Props>(), {
  confirmLabel: 'Confirm',
  processing: false,
  variant: 'destructive',
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
    <DialogContent
      class="max-w-md rounded-none border-border/90 p-0 shadow-[0_24px_60px_-30px_hsl(var(--foreground)/0.7)]"
    >
      <DialogHeader class="gap-0 text-left">
        <div
          class="flex gap-4 border-b px-6 py-5"
          :class="
            variant === 'destructive'
              ? 'border-destructive/20 bg-destructive/10'
              : 'border-amber-700/20 bg-secondary/80'
          "
        >
          <div
            class="flex size-10 shrink-0 items-center justify-center rounded-none"
            :class="
              variant === 'destructive'
                ? 'bg-destructive text-destructive-foreground'
                : 'bg-amber-500 text-amber-950'
            "
          >
            <OctagonAlert v-if="variant === 'destructive'" class="size-5" aria-hidden="true" />
            <TriangleAlert v-else class="size-5" aria-hidden="true" />
          </div>
          <div class="pr-6">
            <DialogTitle class="text-base font-semibold tracking-[-0.01em]">{{
              title
            }}</DialogTitle>
            <DialogDescription class="mt-1.5 text-sm leading-5">{{
              description
            }}</DialogDescription>
          </div>
        </div>
      </DialogHeader>
      <DialogFooter class="gap-2 px-6 py-5 sm:gap-2">
        <Button variant="outline" type="button" :disabled="processing" @click="emit('close')"
          >Cancel</Button
        >
        <Button
          :variant="variant === 'destructive' ? 'destructive' : 'default'"
          type="button"
          :loading="processing"
          loading-text="Processing…"
          @click="emit('confirm')"
          >{{ confirmLabel }}</Button
        >
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
