<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
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

type PayCodeStatus = 'all' | 'awaiting_approval' | 'active' | 'redeemed' | 'expired' | 'pending' | 'failed';

interface Voucher {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    currency?: string | null;
    status?: string | null;
    display_status?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
    starts_at?: string | null;
    mobile?: string | null;
    account_number?: string | null;
    bank_code?: string | null;
    claim_url?: string | null;
    instructions?: Record<string, any> | null;
    approval?: {
        required: boolean;
        type: 'otp' | null;
        provider: string | null;
        reference_id: string | null;
        message: string | null;
        action_url: string | null;
    } | null;
}

interface PaginatedVouchers {
    data: Voucher[];
    links?: any[];
    meta?: Record<string, any>;
}

interface Props {
    vouchers?: Voucher[] | PaginatedVouchers;
    stats?: {
        total?: number;
        active?: number;
        redeemed?: number;
        expired?: number;
        total_amount?: string | number;
        redeemed_amount?: string | number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    vouchers: () => [],
    stats: undefined,
});

const apiVouchers = ref<Voucher[]>([]);
const isLoading = ref(false);

const routes = useXChangeRoutes();

const search = ref('');
const status = ref<PayCodeStatus>('all');

function extractVoucherArray(value: unknown): Voucher[] {
    if (Array.isArray(value)) {
        return value;
    }

    if (!value || typeof value !== 'object') {
        return [];
    }

    const record = value as Record<string, any>;

    if (Array.isArray(record.data)) {
        return record.data;
    }

    if (Array.isArray(record.vouchers)) {
        return record.vouchers;
    }

    if (Array.isArray(record.items)) {
        return record.items;
    }

    if (record.data && typeof record.data === 'object') {
        return extractVoucherArray(record.data);
    }

    if (record.vouchers && typeof record.vouchers === 'object') {
        return extractVoucherArray(record.vouchers);
    }

    return [];
}

const propVouchers = computed<Voucher[]>(() => {
    return extractVoucherArray(props.vouchers);
});

const allVouchers = computed<Voucher[]>(() => {
    const source = propVouchers.value.length > 0
        ? propVouchers.value
        : apiVouchers.value;

    return Array.isArray(source) ? source : [];
});

function isExpired(voucher: Voucher): boolean {
    if (!voucher.expires_at) {
        return false;
    }

    return new Date(voucher.expires_at).getTime() < Date.now();
}

function inferredStatus(voucher: Voucher): string {
    if (voucher.display_status) {
        return String(voucher.display_status).toLowerCase();
    }

    if (voucher.approval?.required === true) {
        return 'awaiting_approval';
    }

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

function approvalUrl(code: string): string {
    if (routes.payCodes?.approval) {
        return routes.payCodes.approval(code);
    }

    return `/x/pay-codes/${code}/approval`;
}

function goToCreate(): void {
    if (routes.payCodes?.create) {
        router.visit(routes.payCodes.create());

        return;
    }

    router.visit('/x/pay-codes/create');
}

async function fetchVouchers(): Promise<void> {
    if (propVouchers.value.length > 0) {
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(routes.api.vouchers, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch vouchers: ${response.status}`);
        }

        const payload = await response.json()

        apiVouchers.value = extractVoucherArray(payload);
    } finally {
        isLoading.value = false;
    }
}

onMounted(fetchVouchers);
</script>

<template>
    <Head title="Pay Codes" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
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
                    <CardTitle class="text-base text-black">
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
                <div v-if="isLoading" class="rounded-lg border border-dashed p-8 text-center">
                    <p class="text-sm text-muted-foreground">Loading Pay Codes…</p>
                </div>

                <PayCodeListTable
                    :vouchers="filteredVouchers"
                    :show-url="showUrl"
                    :claim-url="claimUrl"
                    :approval-url="approvalUrl"
                />
            </CardContent>
        </Card>
    </div>
</template>
