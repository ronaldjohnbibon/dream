import type { PaginationLink } from '@/components/shared/PaginationLinks.vue'

export type CustomerStatus = 'good_standing' | 'overdue' | 'suspended'

export interface Customer {
  id: number
  name: string
  email: string | null
  mobile_number: string | null
  complete_address: string | null
  delivery_area: string | null
  account_status: CustomerStatus
  created_at: string
  updated_at: string
}

export interface CustomerFormData {
  name: string
  email: string
  mobile_number: string
  complete_address: string
  delivery_area: string
  password: string
  password_confirmation: string
}

export interface CustomerFilters {
  search: string
  status: 'all' | CustomerStatus
  sort: 'name' | 'email' | 'mobile_number' | 'created_at'
  direction: 'asc' | 'desc'
}

export interface PaginatedCustomers {
  data: Customer[]
  links: PaginationLink[]
  current_page: number
  last_page: number
  total: number
}

export interface CustomerSummary {
  total_orders: number
  completed_pautang: number
  active_pautang: number
  on_time_payments: number
  late_payments: number
  outstanding_balance: number
  current_points: number
  peso_equivalent: string
}

export interface CustomerOrderHistory {
  id: number
  order_number: string
  order_date: string
  product_name: string
  sack_size: string | null
  quantity: number
  payment_status: 'unpaid' | 'partially_paid' | 'paid' | 'overdue'
  order_status:
    | 'pending'
    | 'confirmed'
    | 'preparing'
    | 'out_for_delivery'
    | 'delivered'
    | 'completed'
    | 'cancelled'
  final_amount: string
  remaining_balance: string
}

export interface CustomerPaymentHistory {
  id: number
  payment_date: string
  amount: string
  status: 'pending_verification' | 'approved' | 'rejected'
  order: { id: number; order_number: string }
  installment_number: number | null
}

export interface CustomerPointsHistory {
  id: number
  type:
    | 'order_reward'
    | 'on_time_payment_bonus'
    | 'redemption'
    | 'redemption_refund'
    | 'admin_adjustment'
  points: number
  description: string
  transaction_date: string
  order: { id: number; order_number: string } | null
  installment_number: number | null
}

export interface CustomerHistoryPagination<T> {
  data: T[]
  links: PaginationLink[]
  current_page: number
  last_page: number
  total: number
}
