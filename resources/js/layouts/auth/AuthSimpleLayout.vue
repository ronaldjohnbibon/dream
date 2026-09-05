<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue'
import { Link } from '@inertiajs/vue3'

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div
    class="relative grid min-h-svh overflow-hidden bg-background lg:grid-cols-[minmax(0,1fr)_minmax(26rem,0.8fr)]"
  >
    <div
      class="relative hidden overflow-hidden bg-primary p-10 text-primary-foreground lg:flex lg:flex-col lg:justify-between xl:p-14"
    >
      <div
        class="absolute -right-28 -top-24 size-96 rounded-full bg-sidebar-primary/20 blur-3xl"
      ></div>
      <div
        class="absolute -bottom-36 -left-24 size-[30rem] rounded-full border-[3rem] border-primary-foreground/10"
      ></div>
      <Link :href="route('home')" class="relative flex items-center gap-2 self-start font-medium"
        ><AppLogo
      /></Link>
      <div class="relative max-w-md space-y-5">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sidebar-primary">
          Rice operations
        </p>
        <h2 class="text-4xl font-semibold leading-tight tracking-[-0.035em]">
          Simple tools for dependable daily service.
        </h2>
        <p class="max-w-sm text-sm leading-6 text-primary-foreground/75">
          Manage orders, deliveries, payments, and customer rewards in one thoughtful workspace.
        </p>
      </div>
      <p class="relative text-xs text-primary-foreground/60">
        Built for a smoother day, from order to delivery.
      </p>
    </div>

    <div class="relative flex items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-sm">
        <div class="flex flex-col gap-8">
          <Link
            :href="route('home')"
            class="flex items-center gap-2 self-center font-medium lg:hidden"
            ><AppLogo
          /></Link>
          <div class="space-y-2 text-center lg:text-left">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-primary">Welcome</p>
            <h1 class="text-2xl font-semibold tracking-[-0.025em]">{{ title }}</h1>
            <p class="text-sm leading-6 text-muted-foreground">{{ description }}</p>
          </div>
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
