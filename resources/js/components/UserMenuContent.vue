<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue'
import {
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import type { User } from '@/types'
import { Link, router } from '@inertiajs/vue3'
import { preparePushLogout } from '@/lib/push-notifications'
import { LogOut, Settings } from 'lucide-vue-next'
import { route } from 'ziggy-js'

interface Props {
  user: User
}

defineProps<Props>()

const logout = async () => {
  const endpoint = await Promise.race([
    preparePushLogout(),
    new Promise<undefined>((resolve) => setTimeout(() => resolve(undefined), 1500)),
  ])
  router.post(route('logout'), endpoint ? { push_endpoint: endpoint } : {})
}
</script>

<template>
  <DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
      <UserInfo :user="user" :show-email="true" />
    </div>
  </DropdownMenuLabel>
  <DropdownMenuSeparator />
  <DropdownMenuGroup>
    <DropdownMenuItem :as-child="true">
      <Link class="block w-full" :href="route('profile.edit')" as="button">
        <Settings class="mr-2 h-4 w-4" />
        Settings
      </Link>
    </DropdownMenuItem>
  </DropdownMenuGroup>
  <DropdownMenuSeparator />
  <DropdownMenuItem :as-child="true">
    <button class="block w-full" type="button" @click="logout">
      <LogOut class="mr-2 h-4 w-4" />
      Log out
    </button>
  </DropdownMenuItem>
</template>
