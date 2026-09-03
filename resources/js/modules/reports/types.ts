import type { PaginationLink } from '@/components/shared/PaginationLinks.vue';

export interface ReportFilters {
    date_from: string;
    date_to: string;
    customer_id: number | null;
    payment_type: 'all' | 'cash' | 'pautang';
    payment_status: 'all' | 'unpaid' | 'partially_paid' | 'paid' | 'overdue';
    order_status: 'all' | 'pending' | 'confirmed' | 'preparing' | 'out_for_delivery' | 'delivered' | 'completed' | 'cancelled';
}

export interface ReportPaginator<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface ReportOrderRow {
    id: number;
    order_number: string;
    customer_name: string;
    product_name: string;
    order_date: string;
    final_amount: string;
    payment_type: string;
    payment_status: string;
    order_status: string;
}

export interface PautangReportRow {
    id: number;
    order_number: string;
    customer_name: string;
    amount_paid: string;
    remaining_balance: string;
    next_due_date: string | null;
    days_overdue: number;
}

export interface BalanceReportRow {
    id: number;
    order_number: string;
    customer_name: string;
    payment_type: string;
    order_date: string;
    remaining_balance: string;
}

export interface OverdueCustomerRow {
    id: number;
    name: string;
    remaining_balance: string;
    oldest_due_date: string;
    days_overdue: number;
}

export interface PaymentReportRow {
    id: number;
    customer_name: string;
    order_number: string;
    payment_type: string | null;
    installment_number: number | null;
    amount: string;
    payment_date: string;
}

export interface InventoryReportRow {
    id: number;
    name: string;
    brand: string;
    available_stock: number;
    reserved_stock: number;
    is_active: boolean;
}

export interface MovementReportRow {
    id: number;
    rice_product: { id: number; name: string; brand: string } | null;
    type: string;
    quantity: number;
    previous_stock: number;
    new_stock: number;
    created_at: string;
}

export interface PointReportRow {
    id: number;
    customer_name: string;
    order_number: string | null;
    type: string;
    points: number;
    description: string;
    transaction_date: string;
}

export interface ProfitReportRow {
    id: number;
    name: string;
    brand: string;
    quantity: number;
    revenue: string;
    cost: string;
    profit: string;
}

export interface ReportSummaries {
    sales: { order_count: number; amount: string; average_order_value: string };
    orders: { count: number; statuses: Record<string, number> };
    pautang: { order_count: number; amount_paid: string; remaining_balance: string };
    collections: { payment_count: number; amount: string };
    outstanding: { order_count: number; amount: string };
    overdue: { customer_count: number; amount: string };
    inventory: { available_stock: number; reserved_stock: number; low_stock_count: number };
    movements: { count: number; stock_in: number; stock_out: number };
    points: { earned: number; redeemed: number };
    profit: { revenue: string; cost: string; profit: string };
}

export interface AgingBucket {
    label: string;
    amount: string;
}
