import type { LucideIcon } from 'lucide-vue-next'
import type { PageProps } from '@inertiajs/core'

export interface Auth {
  user: User | null
}

export interface BreadcrumbItem {
  title: string
  href: string
}

export interface NavItem {
  title: string
  href: string
  icon?: LucideIcon
  isActive?: boolean
}

export interface SharedData extends PageProps {
  name: string
  business: { name: string; logo_url: string | null }
  auth: Auth
  flash: {
    success: string | null
  }
  notifications: {
    unread_count: number
    recent: CustomerNotification[]
  }
  ziggy: {
    location: string
    url: string
    port: null | number
    defaults: Record<string, unknown>
    routes: Record<string, string>
  }
}

export interface User {
  id: number
  name: string
  email: string
  avatar?: string
  is_admin: boolean
}

export interface CustomerNotification {
  id: string
  type: string
  title: string
  message: string
  action_url: string | null
  read_at: string | null
  created_at: string
}

export interface PaginatedNotifications {
  data: CustomerNotification[]
  links: { label: string; url: string | null; active: boolean }[]
}

export type BreadcrumbItemType = BreadcrumbItem
