import type { PaginationLink } from '@/components/shared/PaginationLinks.vue';

export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string;
    updated_at: string;
}

export interface UserFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    is_admin: boolean;
}

export interface UserFilters {
    search: string;
    account_type: 'all' | 'admin' | 'user';
    sort: 'name' | 'email' | 'created_at';
    direction: 'asc' | 'desc';
}

export interface PaginatedUsers {
    data: ManagedUser[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}
