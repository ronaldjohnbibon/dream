import type { PaginationLink } from '@/components/shared/PaginationLinks.vue';

export type PaymentType = 'cash' | 'pautang';
export type OrderStatus = 'pending' | 'confirmed' | 'preparing' | 'out_for_delivery' | 'delivered' | 'completed' | 'cancelled';
export type PaymentStatus = 'unpaid' | 'partially_paid' | 'paid' | 'overdue';
export type PautangInstallmentStatus = 'pending' | 'partially_paid' | 'paid' | 'overdue';
export type GcashPaymentStatus = 'pending_verification' | 'approved' | 'rejected';

export const paymentTypeLabels: Record<PaymentType, string> = {
    cash: 'Cash',
    pautang: 'Pautang',
};

export const orderStatusLabels: Record<OrderStatus, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    preparing: 'Preparing',
    out_for_delivery: 'Out for Delivery',
    delivered: 'Delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const paymentStatusLabels: Record<PaymentStatus, string> = {
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
    paid: 'Paid',
    overdue: 'Overdue',
};

export const gcashPaymentStatusLabels: Record<GcashPaymentStatus, string> = {
    pending_verification: 'Pending Verification',
    approved: 'Approved',
    rejected: 'Rejected',
};

export interface Order {
    id: number;
    order_number: string;
    customer: {
        id: number;
        name: string;
        email: string | null;
        mobile_number: string | null;
    };
    rice_product: {
        id: number;
        name: string;
        brand: string;
        sack_size: string;
    };
    quantity: number;
    unit_price: string;
    subtotal: string;
    points_used: number;
    points_discount: string;
    final_amount: string;
    amount_paid: string;
    remaining_balance: string;
    payment_type: PaymentType;
    delivery_address: string;
    delivery_area: string;
    order_date: string;
    delivery_date: string | null;
    order_status: OrderStatus;
    payment_status: PaymentStatus;
    notes: string | null;
    pautang_installments: PautangInstallment[];
    gcash_payments: GcashPayment[];
    created_at: string;
    updated_at: string;
}

export interface GcashPayment {
    id: number;
    amount: string;
    reference_number: string;
    payment_date: string;
    status: GcashPaymentStatus;
    remarks: string | null;
    reviewed_at: string | null;
    screenshot_url: string;
    installment_number: number | null;
    reviewer_name: string | null;
}

export interface AdminGcashPayment {
    id: number;
    amount: string;
    reference_number: string;
    payment_date: string;
    status: GcashPaymentStatus;
    remarks: string | null;
    reviewed_at: string | null;
    screenshot_url: string;
    order: {
        id: number;
        order_number: string;
        final_amount?: string;
        amount_paid?: string;
        remaining_balance?: string;
    };
    customer: Order['customer'];
    installment: {
        id: number;
        installment_number: number;
        amount_due?: string;
        amount_paid?: string;
        remaining_balance?: string;
    } | null;
    reviewer: { id: number; name: string } | null;
    created_at: string;
}

export interface PaginatedGcashPayments {
    data: AdminGcashPayment[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface PautangInstallment {
    id: number;
    installment_number: number;
    amount_due: string;
    due_date: string;
    amount_paid: string;
    remaining_balance: string;
    status: PautangInstallmentStatus;
    paid_date: string | null;
}

export interface PautangSummary {
    id: number;
    order_number: string;
    customer: Order['customer'] | null;
    order_amount: string;
    amount_paid: string;
    remaining_balance: string;
    next_due_date: string | null;
    days_overdue: number;
    installments: PautangInstallment[];
}

export interface PaginatedPautangOrders {
    data: PautangSummary[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface OrderFilters {
    search: string;
    order_status: 'all' | OrderStatus;
    payment_type: 'all' | PaymentType;
    payment_status: 'all' | PaymentStatus;
}

export interface PaginatedOrders {
    data: Order[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface AvailableRiceProduct {
    id: number;
    name: string;
    brand: string;
    selling_price: string;
    available_stock: number;
}
