<script setup lang="ts">
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { SidebarTrigger } from '@/components/ui/sidebar'
import type { BreadcrumbItemType, SharedData, User } from '@/types'
import { usePage } from '@inertiajs/vue3'
import { CircleUserRound } from 'lucide-vue-next'
import { computed } from 'vue'
import NotificationBell from './NotificationBell.vue'
import UserMenuContent from './UserMenuContent.vue'

defineProps<{
  breadcrumbs: BreadcrumbItemType[]
}>()

const page = usePage<SharedData>()
const isCustomer = computed(() => page.props.auth.user?.is_admin === false)
const user = computed(() => page.props.auth.user as User)
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex items-center gap-2">
      <SidebarTrigger v-if="!isCustomer" class="-ml-1" />
      <template v-if="breadcrumbs.length > 0">
        <Breadcrumb>
          <BreadcrumbList :class="isCustomer ? 'hidden md:flex' : ''">
            <template v-for="(item, index) in breadcrumbs" :key="index">
              <BreadcrumbItem>
                <template v-if="index === breadcrumbs.length - 1">
                  <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                </template>
                <template v-else>
                  <BreadcrumbLink :href="item.href">
                    {{ item.title }}
                  </BreadcrumbLink>
                </template>
              </BreadcrumbItem>
              <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
          </BreadcrumbList>
        </Breadcrumb>
      </template>
    </div>
    <div class="flex items-center gap-1">
      <NotificationBell />
      <DropdownMenu v-if="isCustomer && user">
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" size="icon" aria-label="Account menu"
            ><CircleUserRound class="size-5"
          /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-56"
          ><UserMenuContent :user="user"
        /></DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
