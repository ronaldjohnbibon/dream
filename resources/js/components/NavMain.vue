<script setup lang="ts">
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import type { NavItem, SharedData } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'

defineProps<{
  items: NavItem[]
}>()

const page = usePage<SharedData>()

const isActive = (href: string) =>
  page.url === href ||
  page.url.startsWith(`${href}?`) ||
  (href !== route('dashboard') && page.url.startsWith(`${href}/`))
</script>

<template>
  <SidebarGroup class="px-1 py-2">
    <SidebarGroupLabel
      class="px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-sidebar-foreground/55"
      >Workspace</SidebarGroupLabel
    >
    <SidebarMenu>
      <SidebarMenuItem v-for="item in items" :key="item.title">
        <SidebarMenuButton
          as-child
          :is-active="isActive(item.href)"
          class="h-10 rounded-lg px-3 text-sidebar-foreground/80 transition-all hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[active=true]:bg-sidebar-primary data-[active=true]:font-semibold data-[active=true]:text-sidebar-primary-foreground data-[active=true]:shadow-sm"
        >
          <Link :href="item.href">
            <component :is="item.icon" v-if="item.icon" />
            <span>{{ item.title }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
