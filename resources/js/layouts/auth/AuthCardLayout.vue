<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Link } from '@inertiajs/vue3'

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
    <div class="flex w-full max-w-md flex-col gap-6">
      <Link :href="route('home')" class="flex items-center gap-2 self-center font-medium"
        ><AppLogo
      /></Link>

      <div class="flex flex-col gap-6">
        <Card class="border-border/80 shadow-[0_24px_60px_-36px_hsl(var(--foreground)/0.55)]">
          <CardHeader class="px-7 pb-0 pt-8 text-center sm:px-10">
            <CardTitle class="text-2xl tracking-[-0.025em]">{{ title }}</CardTitle>
            <CardDescription>
              {{ description }}
            </CardDescription>
          </CardHeader>
          <CardContent class="px-7 py-8 sm:px-10">
            <slot />
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
