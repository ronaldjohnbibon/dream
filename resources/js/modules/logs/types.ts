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
}

export interface PaginatedSystemLogs {
  data: SystemLog[]
  links: { label: string; url: string | null; active: boolean }[]
}
