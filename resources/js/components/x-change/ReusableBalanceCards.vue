<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, WalletCards } from 'lucide-vue-next';
import StoredValueInstrumentPageController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/StoredValueInstrumentPageController';

export type ReusableBalanceSummary = {
    schema: string;
    reference: string;
    status: 'active' | 'low_balance' | 'depleted' | 'expired' | 'closed' | 'unavailable';
    currency: string;
    available_minor: number | null;
    total_loaded_minor: number | null;
    total_spent_minor: number | null;
    maximum_minor: number | null;
    replenishable: boolean | null;
    activated_at: string | null;
    expires_at: string | null;
};

defineProps<{
    balances: ReusableBalanceSummary[];
}>();

const statusLabel = (status: ReusableBalanceSummary['status']) => ({
    active: 'Active',
    low_balance: 'Low balance',
    depleted: 'Depleted',
    expired: 'Expired',
    closed: 'Closed',
    unavailable: 'Balance unavailable',
}[status]);

const formatMoney = (minor: number | null, currency: string) => {
    if (minor === null) {
        return 'Balance unavailable';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(minor / 100);
};
</script>

<template>
    <section v-if="balances.length > 0" class="space-y-3" data-testid="reusable-balances">
        <div class="space-y-1">
            <h2 class="text-base font-semibold">Reusable balances</h2>
            <p class="text-sm text-muted-foreground">
                Value you can spend more than once after activating a reusable Pay Code.
            </p>
        </div>

        <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="balance in balances"
                :key="balance.reference"
                :href="StoredValueInstrumentPageController({ instrument: balance.reference }).url"
                class="group min-w-0 rounded-xl border bg-card p-4 text-card-foreground shadow-sm transition hover:border-primary/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :data-testid="`reusable-balance-${balance.reference}`"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <WalletCards class="size-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 items-center justify-between gap-2">
                            <p class="truncate text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Reusable balance
                            </p>
                            <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium">
                                {{ statusLabel(balance.status) }}
                            </span>
                        </div>
                        <p class="mt-2 text-xl font-semibold tabular-nums">
                            {{ formatMoney(balance.available_minor, balance.currency) }}
                        </p>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                            <span class="truncate">{{ balance.reference }}</span>
                            <ChevronRight class="size-4 shrink-0 transition group-hover:translate-x-0.5" aria-hidden="true" />
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
