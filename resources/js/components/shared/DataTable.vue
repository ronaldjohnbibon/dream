<script setup lang="ts">
interface Props {
  emptyMessage?: string
}

withDefaults(defineProps<Props>(), {
  emptyMessage: 'No records found.',
})
</script>

<template>
  <div
    class="overflow-hidden rounded-xl border border-border/80 bg-card shadow-[0_10px_30px_-24px_hsl(var(--foreground)/0.45)]"
  >
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead
          class="bg-secondary/65 text-left text-xs font-semibold uppercase tracking-[0.05em] text-muted-foreground"
        >
          <slot name="head" />
        </thead>
        <tbody class="divide-y divide-border/70 [&_tr]:transition-colors [&_tr:hover]:bg-muted/35">
          <slot name="body" />
        </tbody>
      </table>
    </div>
    <div v-if="$slots.body === undefined" class="p-8 text-center text-sm text-muted-foreground">
      {{ emptyMessage }}
    </div>
  </div>
</template>
