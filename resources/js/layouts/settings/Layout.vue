<script setup lang="ts">
import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { type NavItem, type SharedData } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage<SharedData>()
const sidebarNavItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    {
      title: 'Profile',
      href: '/settings/profile',
    },
    {
      title: 'Password',
      href: '/settings/password',
    },
  ]

  if (page.props.auth.user?.is_admin) {
    items.push({ title: 'System', href: '/settings/system' })
  }

  return items
})

const currentPath = window.location.pathname
</script>

<template>
  <div class="app-page max-w-6xl gap-0">
    <Heading title="Settings" description="Manage your profile, account, and business settings" />

    <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
      <aside class="w-full max-w-xl lg:w-52">
        <nav class="surface-toolbar flex flex-col space-x-0 space-y-1 lg:sticky lg:top-24">
          <Button
            v-for="item in sidebarNavItems"
            :key="item.href"
            variant="ghost"
            :class="[
              'w-full justify-start text-foreground',
              {
                'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground':
                  currentPath === item.href,
              },
            ]"
            as-child
          >
            <Link :href="item.href">
              {{ item.title }}
            </Link>
          </Button>
        </nav>
      </aside>

      <Separator class="my-6 md:hidden" />

      <div class="flex-1 md:max-w-2xl">
        <section class="max-w-xl space-y-12">
          <slot />
        </section>
      </div>
    </div>
  </div>
</template>
