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
