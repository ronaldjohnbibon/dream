<script setup lang="ts">
import type { SharedData } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { Award, Bell, HandCoins, Home, ShoppingCart } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage<SharedData>()

const items = [
  { title: 'Home', href: route('dashboard'), icon: Home },
  { title: 'Orders', href: route('orders.index'), icon: ShoppingCart },
  { title: 'Pautang', href: route('pautang.index'), icon: HandCoins },
  { title: 'Points', href: route('points.show'), icon: Award },
  { title: 'Alerts', href: route('notifications.index'), icon: Bell },
]

const isActive = (href: string) =>
  page.url === href ||
  page.url.startsWith(`${href}?`) ||
  (href !== route('dashboard') && page.url.startsWith(`${href}/`))
const unreadCount = computed(() => page.props.notifications?.unread_count ?? 0)
</script>

<template>
  <nav
    aria-label="Customer navigation"
    class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-background/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-8px_24px_-16px_hsl(var(--foreground)/0.35)] backdrop-blur-xl md:hidden"
  >
    <ul class="mx-auto grid max-w-lg grid-cols-5 gap-1">
      <li v-for="item in items" :key="item.title">
        <Link
          :href="item.href"
          class="relative flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1 text-[11px] font-semibold transition-all"
          :class="
            isActive(item.href)
              ? '-translate-y-1 bg-primary text-primary-foreground shadow-[0_8px_16px_-10px_hsl(var(--primary)/0.9)]'
              : 'text-muted-foreground hover:bg-muted hover:text-foreground'
          "
          :aria-current="isActive(item.href) ? 'page' : undefined"
        >
          <component :is="item.icon" class="size-4" />
          <span>{{ item.title }}</span>
          <span
            v-if="item.title === 'Alerts' && unreadCount"
            class="absolute right-2 top-0 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 text-destructive-foreground"
            >{{ unreadCount > 9 ? '9+' : unreadCount }}</span
          >
        </Link>
      </li>
    </ul>
  </nav>
</template>
