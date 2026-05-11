<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    PayCodeFilters,
    PayCodeListTable,
    PayCodeStatsCards,
} from '@/components/x-change/pay-codes';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import { PlusCircle } from 'lucide-vue-next';

defineOptions({
    layout: XChangeLayout,
});

type PayCodeStatus = 'all' | 'active' | 'redeemed' | 'expired' | 'pending' | 'failed';

interface Voucher {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    currency?: string | null;
    status?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
    starts_at?: string | null;
    mobile?: string | null;
    account_number?: string | null;
    bank_code?: string | null;
    claim_url?: string | null;
    instructions?: Record<string, any> | null;
}

interface PaginatedVouchers {
    data: Voucher[];
    links?: any[];
    meta?: Record<string, any>;
}

interface Props {
    vouchers: Voucher[] | PaginatedVouchers;
    stats?: {
        total?: number;
        active?: number;
        redeemed?: number;
        expired?: number;
        total_amount?: string | number;
        redeemed_amount?: string | number;
    };
}

const props = defineProps<Props>();

const routes = useXChangeRoutes();

const search = ref('');
const status = ref<PayCodeStatus>('all');

const allVouchers = computed<Voucher[]>(() => {
    if (Array.isArray(props.vouchers)) {
        return props.vouchers;
    }

    return props.vouchers?.data ?? [];
});

function isExpired(voucher: Voucher): boolean {
    if (!voucher.expires_at) {
        return false;
    }

    return new Date(voucher.expires_at).getTime() < Date.now();
}

function inferredStatus(voucher: Voucher): string {
    if (voucher.status) {
        return String(voucher.status).toLowerCase();
    }

    if (voucher.redeemed_at) {
        return 'redeemed';
    }

    if (isExpired(voucher)) {
        return 'expired';
    }

    return 'active';
}

function matchesSearch(voucher: Voucher): boolean {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return true;
    }

    const searchable = [
        voucher.code,
        voucher.mobile,
        voucher.account_number,
        voucher.bank_code,
        voucher.status,
        voucher.formatted_amount,
        voucher.amount,
    ]
        .filter((value) => value !== null && value !== undefined)
        .map((value) => String(value).toLowerCase());

    return searchable.some((value) => value.includes(term));
}

function matchesStatus(voucher: Voucher): boolean {
    if (status.value === 'all') {
        return true;
    }

    return inferredStatus(voucher) === status.value;
}

const filteredVouchers = computed(() => {
    return allVouchers.value
        .filter(matchesSearch)
        .filter(matchesStatus);
});

const computedStats = computed(() => {
    if (props.stats) {
        return props.stats;
    }

    return {
        total: allVouchers.value.length,
        active: allVouchers.value.filter((voucher) => inferredStatus(voucher) === 'active').length,
        redeemed: allVouchers.value.filter((voucher) => inferredStatus(voucher) === 'redeemed').length,
        expired: allVouchers.value.filter((voucher) => inferredStatus(voucher) === 'expired').length,
    };
});

function showUrl(code: string): string {
    if (routes.payCodes?.show) {
        return routes.payCodes.show(code);
    }

    return `/x/pay-codes/${code}`;
}

function claimUrl(code: string): string {
    if (routes.claim?.startWithCode) {
        return routes.claim.startWithCode(code);
    }

    return `/x/claim?code=${code}`;
}

function goToCreate(): void {
    if (routes.payCodes?.create) {
        router.visit(routes.payCodes.create());

        return;
    }

    router.visit('/x/pay-codes/create');
}
</script>

<template>
    <Head title="Pay Codes" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Pay Codes
                </h1>
                <p class="text-sm text-muted-foreground">
                    Generate, monitor, and manage disburseable Pay Codes.
                </p>
            </div>

            <Button @click="goToCreate">
                <PlusCircle class="mr-2 h-4 w-4" />
                Generate Pay Code
            </Button>
        </div>

        <!-- Stats -->
        <PayCodeStatsCards :stats="computedStats" />

        <!-- List -->
        <Card>
            <CardHeader class="space-y-4">
                <div class="flex flex-col gap-1">
                    <CardTitle class="text-base">
                        Pay Code Registry
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Search and inspect generated Pay Codes.
                    </p>
                </div>

                <PayCodeFilters
                    v-model:search="search"
                    v-model:status="status"
                />
            </CardHeader>

            <CardContent>
                <PayCodeListTable
                    :vouchers="filteredVouchers"
                    :show-url="showUrl"
                    :claim-url="claimUrl"
                />
            </CardContent>
        </Card>
    </div>
</template>
