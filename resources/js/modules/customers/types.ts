import type { PaginationLink } from '@/components/shared/PaginationLinks.vue';

export type CustomerStatus = 'good_standing' | 'overdue' | 'suspended';

export interface Customer {
    id: number;
    name: string;
    email: string | null;
    mobile_number: string | null;
    complete_address: string | null;
    delivery_area: string | null;
    account_status: CustomerStatus;
    created_at: string;
    updated_at: string;
}

export interface CustomerFormData {
    name: string;
    email: string;
    mobile_number: string;
    complete_address: string;
    delivery_area: string;
    password: string;
    password_confirmation: string;
}

export interface CustomerFilters {
    search: string;
    status: 'all' | CustomerStatus;
    sort: 'name' | 'email' | 'mobile_number' | 'created_at';
    direction: 'asc' | 'desc';
}

export interface PaginatedCustomers {
    data: Customer[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface CustomerSummary {
    total_orders: number;
    completed_pautang: number;
    active_pautang: number;
    on_time_payments: number;
    late_payments: number;
    outstanding_balance: number;
    current_points: number;
}
