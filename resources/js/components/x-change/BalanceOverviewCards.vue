<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, Clock, RefreshCw, WalletCards } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface BalanceEntry {
    key: string;
    label: string;
    description?: string | null;
    authority: string;
    source: string;
    is_authoritative: boolean;
    is_stale: boolean;
    balance: number | null;
    balance_minor?: number | null;
    currency: string;
    checked_at?: string | null;
    provider_wallet_id?: string | null;
    sync_status?: string | null;
    sync_message?: string | null;
}

export interface BalanceOverview {
    provider: string;
    topology: string;
    authority: string;
    checked_at: string;
    max_age_seconds: number;
    sync_status: string;
    sync_message: string;
    authoritative?: BalanceEntry | null;
    balances: BalanceEntry[];
}

const props = withDefaults(defineProps<{
    overview?: BalanceOverview | null;
    compact?: boolean;
    requiredAmount?: number | null;
}>(), {
    overview: null,
    compact: false,
    requiredAmount: null,
});

const balances = computed(() => props.overview?.balances ?? []);
const authoritative = computed(() => props.overview?.authoritative ?? balances.value.find((balance) => balance.is_authoritative) ?? null);
const projections = computed(() => balances.value.filter((balance) => !balance.is_authoritative));
const isProviderWalletAuthority = computed(() => props.overview?.authority === 'provider_wallet');
const remainingAfterRequired = computed(() => {
    if (!authoritative.value || authoritative.value.balance === null || props.requiredAmount === null) {
        return null;
    }

    return authoritative.value.balance - props.requiredAmount;
});

function money(value: number | null | undefined, currency = 'PHP'): string {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) {
        return 'Unavailable';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(Number(value));
}

function relativeTime(value?: string | null): string {
    if (!value) {
        return 'Not synced';
    }

    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function authorityLabel(value?: string | null): string {
    const labels: Record<string, string> = {
        provider_wallet: 'Provider wallet balance',
        local_ledger: 'Local ledger',
        manual: 'Manual ledger',
    };

    return labels[value ?? ''] ?? 'Balance authority';
}

function syncTone(status?: string | null): string {
    if (status === 'synced' || status === 'fresh' || status === 'not_required') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (status === 'stale') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }

    return 'border-red-200 bg-red-50 text-red-800';
}
</script>

<template>
    <Card>
        <CardHeader :class="compact ? 'space-y-2 pb-3' : 'space-y-3'">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <CardTitle :class="compact ? 'text-base' : 'text-lg'">
                        Spendable Balance
                    </CardTitle>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ authorityLabel(overview?.authority) }} is used to decide whether Pay Code generation can proceed.
                    </p>
                </div>

                <span
                    v-if="overview"
                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="syncTone(overview.sync_status)"
                >
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ overview.sync_status.replace(/_/g, ' ') }}
                </span>
            </div>
        </CardHeader>

        <CardContent :class="compact ? 'space-y-3' : 'space-y-4'">
            <div v-if="!overview" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                Balance data is not available yet.
            </div>

            <template v-else>
                <div
                    v-if="authoritative"
                    class="overflow-hidden rounded-2xl border bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-4 shadow-sm dark:from-emerald-950/40 dark:via-slate-950 dark:to-slate-900"
                    :class="{ 'border-amber-300 bg-amber-50/70': authoritative.is_stale }"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <WalletCards class="h-4 w-4 text-emerald-700 dark:text-emerald-300" />
                                <p class="text-sm font-medium">{{ authoritative.label }}</p>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    isProviderWalletAuthority
                                        ? 'This is the spendable provider balance used for Pay Code issuance.'
                                        : authoritative.description
                                }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-full bg-emerald-700 px-2 py-1 text-[11px] font-medium text-white dark:bg-emerald-500 dark:text-emerald-950">
                            Used for issuance
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                {{ isProviderWalletAuthority ? 'Spendable' : 'Available' }}
                            </p>
                            <p :class="compact ? 'text-2xl font-bold' : 'text-3xl font-bold'">
                                {{ money(authoritative.balance, authoritative.currency) }}
                            </p>
                        </div>

                        <div v-if="remainingAfterRequired !== null" class="text-right">
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">After Estimate</p>
                            <p
                                class="text-lg font-semibold"
                                :class="remainingAfterRequired < 0 ? 'text-destructive' : 'text-emerald-700'"
                            >
                                {{ money(remainingAfterRequired, authoritative.currency) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <Clock class="h-3.5 w-3.5" />
                            Synced {{ relativeTime(authoritative.checked_at) }}
                        </span>
                        <span v-if="authoritative.provider_wallet_id">
                            Wallet {{ authoritative.provider_wallet_id }}
                        </span>
                        <span v-if="authoritative.is_stale" class="inline-flex items-center gap-1 text-amber-700">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            Stale
                        </span>
                        <span v-else class="inline-flex items-center gap-1 text-emerald-700">
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Fresh
                        </span>
                    </div>

                    <p v-if="overview.sync_message" class="mt-3 text-xs text-muted-foreground">
                        {{ overview.sync_message }}
                    </p>
                </div>

                <div v-if="!compact && projections.length > 0" class="rounded-xl border border-dashed bg-muted/20 p-3">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                Accounting projection
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Shown for reconciliation only. This is not the spendable balance for Paynamics issuance.
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <div
                            v-for="balance in projections"
                            :key="balance.key"
                            class="rounded-lg border bg-background/70 p-3 opacity-80"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium">{{ balance.label }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ balance.description }}</p>
                                </div>
                                <span class="rounded-full bg-muted px-2 py-1 text-[11px] text-muted-foreground">
                                    Not spendable
                                </span>
                            </div>
                            <p class="mt-2 text-lg font-semibold text-muted-foreground">
                                {{ money(balance.balance, balance.currency) }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
