export interface ActivityLog {
  id: number
  user_name: string
  module: string
  action: string
  related_record: string
  description: string
  created_at: string
}

export interface ActivityLogFilters {
  search: string
  module: string
  action: string
}

export interface PaginatedActivityLogs {
  data: ActivityLog[]
  links: { label: string; url: string | null; active: boolean }[]
}

export interface SystemLog {
  id: number
  type: string
  action: string
  module: string | null
  description: string
  status: string | null
  user: { id: number; name: string } | null
  created_at: string
}

export interface SystemLogDetails extends SystemLog {
  record_id: number | null
  ip_address: string | null
  user_agent: string | null
  metadata: Record<string, unknown> | null
}

export interface SystemLogFilters {
  search: string
  date_from: string
  date_to: string
  type: string
  module: string
  action: string
  user_id: number | null
  status: string
  severity: 'all' | 'error' | 'warning'
  category:
    | 'all'
    | 'payments'
    | 'orders'
    | 'points'
    | 'notifications'
    | 'security'
    | 'authentication'
    | 'scheduler'
    | 'system'
}

export interface PaginatedSystemLogs {
  data: SystemLog[]
  links: { label: string; url: string | null; active: boolean }[]
}

export interface SystemLogSummary {
  total_events: number
  errors: number
  warnings: number
  security_events: number
  failed_notifications: number
  successful_notifications: number
}

export interface SystemLogAttentionItem {
  action: string
  module: string | null
  count: number
  latest_at: string
}

export interface SystemLogHealthSignal {
  key: 'web_push' | 'scheduler' | 'security'
  label: string
  state: 'attention' | 'recent_success' | 'no_data'
  count: number
  latest_at: string | null
}

export interface SystemLogCategory {
  key: Exclude<SystemLogFilters['category'], 'all'>
  label: string
  logs: SystemLog[]
}

export interface SystemLogsDashboard {
  summary: SystemLogSummary
  attention: SystemLogAttentionItem[]
  health: SystemLogHealthSignal[]
  categories: SystemLogCategory[]
}
