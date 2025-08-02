import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;

    wallet?: {
        id: number;
        type: string | null;
    };
}

export interface Voucher {
    code: string;
    instructions: Record<string, any>;
    cash: {
        amount: number;
        currency: string;
        withdrawTransaction?: {
            confirmed: boolean;
            payload: {
                destination_account: {
                    bank_code: string;
                    account_number: string;
                };
            };
        };
    };
    metadata: Record<string, any>;
    inputs: {
        name: string;
        value: string;
    }[];
    contact?: {
        mobile: string;
        country?: string;
        bank_code?: string;
        account_number?: string;
        bank_account?: string;
        name?: string;
    } | null;
    created_at: string;
    starts_at: string;
    redeemed_at: string;
    disbursed: boolean;
    expired_at: string;
}
export type VoucherList = Voucher[];

export interface MessageData {
    subject: string
    title: string
    body: string
    closing: string
}

export type BreadcrumbItemType = BreadcrumbItem;
