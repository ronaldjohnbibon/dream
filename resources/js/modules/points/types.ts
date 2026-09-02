import type { PaginationLink } from '@/components/shared/PaginationLinks.vue';

export type PointsLedgerType = 'order_reward' | 'on_time_payment_bonus' | 'redemption' | 'redemption_refund' | 'admin_adjustment';

export interface PointsLedgerEntry {
    id: number;
    type: PointsLedgerType;
    points: number;
    description: string;
    transaction_date: string;
    order: { id: number; order_number: string } | null;
    installment_number: number | null;
}

export interface PaginatedPointsLedger {
    data: PointsLedgerEntry[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}
