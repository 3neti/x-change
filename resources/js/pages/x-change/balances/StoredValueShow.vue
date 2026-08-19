<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowLeft, ArrowUpRight, History, WalletCards } from 'lucide-vue-next';
import StoredValueInstrumentPageController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/StoredValueInstrumentPageController';
import type { ReusableBalanceSummary } from '@/components/x-change/ReusableBalanceCards.vue';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';

type Movement = {
    type: string;
    label: string;
    amount_minor: number;
    balance_after_minor: number;
    currency: string;
    occurred_at: string;
};

type InstrumentDetail = ReusableBalanceSummary & {
    activity_available: boolean;
    transactions: Movement[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
};

defineOptions({
    layout: [XChangeLayout, {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Balances', href: '/x/balances' },
            { title: 'Reusable balance' },
        ],
    }],
});

const props = defineProps<{
    instrument: InstrumentDetail;
}>();

const formatMoney = (minor: number | null, currency = props.instrument.currency) => {
    if (minor === null) {
        return 'Unavailable';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
        signDisplay: 'auto',
    }).format(minor / 100);
};

const formatDate = (value: string | null) => value
    ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';

const statusLabel = (status: ReusableBalanceSummary['status']) => ({
    active: 'Active',
    low_balance: 'Low balance',
    depleted: 'Depleted',
    expired: 'Expired',
    closed: 'Closed',
    unavailable: 'Balance unavailable',
}[status]);

const pageUrl = (page: number) => StoredValueInstrumentPageController(
    { instrument: props.instrument.reference },
    { query: { page } },
).url;
</script>

<template>
    <Head title="Reusable balance" />

    <main class="mx-auto flex w-full max-w-5xl min-w-0 flex-1 flex-col gap-5 p-4 sm:p-6" data-testid="stored-value-detail">
        <Link href="/x/balances" class="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" />
            Back to balances
        </Link>

        <section class="overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-sm">
            <div class="bg-slate-950 p-5 text-white sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-slate-300">
                            <WalletCards class="size-5" aria-hidden="true" />
                            <span class="text-xs font-semibold tracking-[0.16em] uppercase">Reusable balance</span>
                        </div>
                        <p class="mt-4 text-3xl font-semibold tabular-nums sm:text-4xl">
                            {{ formatMoney(instrument.available_minor) }}
                        </p>
                        <p class="mt-1 text-sm text-slate-300">Available to spend</p>
                    </div>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-medium">
                        {{ statusLabel(instrument.status) }}
                    </span>
                </div>
            </div>

            <dl class="grid gap-px bg-border sm:grid-cols-3">
                <div class="bg-card p-4">
                    <dt class="text-xs text-muted-foreground">Total loaded</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ formatMoney(instrument.total_loaded_minor) }}</dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-xs text-muted-foreground">Total spent</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ formatMoney(instrument.total_spent_minor) }}</dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-xs text-muted-foreground">Policy</dt>
                    <dd class="mt-1 font-semibold">{{ instrument.replenishable ? 'Reloadable' : 'Not reloadable' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border bg-card p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <History class="size-5 text-muted-foreground" aria-hidden="true" />
                <h2 class="font-semibold">Transactions</h2>
            </div>

            <div v-if="!instrument.activity_available" class="py-10 text-center text-sm text-muted-foreground">
                Transaction history is temporarily unavailable.
            </div>
            <div v-else-if="instrument.transactions.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                No balance activity yet.
            </div>
            <ol v-else class="mt-4 divide-y">
                <li v-for="movement in instrument.transactions" :key="`${movement.occurred_at}-${movement.type}-${movement.balance_after_minor}`" class="flex min-w-0 items-center gap-3 py-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                        <ArrowDownLeft v-if="movement.amount_minor > 0" class="size-4 text-emerald-600" aria-hidden="true" />
                        <ArrowUpRight v-else class="size-4 text-amber-600" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ movement.label }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatDate(movement.occurred_at) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold tabular-nums" :class="movement.amount_minor > 0 ? 'text-emerald-600' : ''">
                            {{ formatMoney(movement.amount_minor, movement.currency) }}
                        </p>
                        <p class="text-xs text-muted-foreground">Balance {{ formatMoney(movement.balance_after_minor, movement.currency) }}</p>
                    </div>
                </li>
            </ol>

            <nav v-if="instrument.pagination.last_page > 1" class="mt-4 flex items-center justify-between border-t pt-4" aria-label="Transaction pages">
                <Link
                    v-if="instrument.pagination.current_page > 1"
                    :href="pageUrl(instrument.pagination.current_page - 1)"
                    class="rounded-lg border px-3 py-2 text-sm hover:bg-muted"
                >Previous</Link>
                <span v-else />
                <span class="text-xs text-muted-foreground">Page {{ instrument.pagination.current_page }} of {{ instrument.pagination.last_page }}</span>
                <Link
                    v-if="instrument.pagination.current_page < instrument.pagination.last_page"
                    :href="pageUrl(instrument.pagination.current_page + 1)"
                    class="rounded-lg border px-3 py-2 text-sm hover:bg-muted"
                >Next</Link>
                <span v-else />
            </nav>
        </section>

        <p class="break-all text-center text-xs text-muted-foreground">Instrument {{ instrument.reference }}</p>
    </main>
</template>
