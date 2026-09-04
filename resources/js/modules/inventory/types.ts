import type { PaginationLink } from '@/components/shared/PaginationLinks.vue'

export type MovementType =
  'stock_in' | 'order' | 'cancellation' | 'adjustment' | 'damaged' | 'returned'

export const movementTypeLabels: Record<MovementType, string> = {
  stock_in: 'Stock In',
  order: 'Order',
  cancellation: 'Cancellation',
  adjustment: 'Adjustment',
  damaged: 'Damaged',
  returned: 'Returned',
}

export interface RiceProduct {
  id: number
  name: string
  brand: string
  description: string | null
  sack_size: string
  cost_price: string
  selling_price: string
  available_stock: number
  reserved_stock: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface RiceProductFormData {
  name: string
  brand: string
  description: string
  cost_price: string
  selling_price: string
  initial_stock: number
}

export interface ProductFilters {
  search: string
  status: 'all' | 'active' | 'inactive'
  low_stock: boolean
}

export interface MovementFilters {
  search: string
  type: 'all' | MovementType
}

export interface StockAdjustmentFormData {
  type: MovementType
  quantity: number
  adjustment_direction: 'increase' | 'decrease'
  order_id: number | null
  notes: string
}

export interface StockMovement {
  id: number
  quantity: number
  type: MovementType
  previous_stock: number
  new_stock: number
  order_id: number | null
  notes: string | null
  user_name: string
  created_at: string
}

export interface StockMovementWithProduct extends StockMovement {
  rice_product: Pick<RiceProduct, 'id' | 'name' | 'brand'>
}

export interface PaginatedProducts {
  data: RiceProduct[]
  links: PaginationLink[]
  current_page: number
  last_page: number
  total: number
}

export interface PaginatedMovements {
  data: StockMovementWithProduct[]
  links: PaginationLink[]
  current_page: number
  last_page: number
  total: number
}
