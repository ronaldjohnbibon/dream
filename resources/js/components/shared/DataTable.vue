<script setup lang="ts">
interface Props {
  emptyMessage?: string
}

withDefaults(defineProps<Props>(), {
  emptyMessage: 'No records found.',
})
</script>

<template>
  <div class="overflow-hidden rounded-lg border bg-card">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left text-muted-foreground">
          <slot name="head" />
        </thead>
        <tbody class="divide-y">
          <slot name="body" />
        </tbody>
      </table>
    </div>
    <div v-if="$slots.body === undefined" class="p-8 text-center text-sm text-muted-foreground">
      {{ emptyMessage }}
    </div>
  </div>
</template>
