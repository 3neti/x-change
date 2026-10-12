<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import CockpitPayCodeResultsTable from '../../../cockpit/components/CockpitPayCodeResultsTable.vue';
import type {
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from '../../../cockpit/types';

defineOptions({ layout: null });

const props = defineProps<{
    batch_reference: string;
    batch_endpoint: string;
    download_endpoint: string;
    auto_generate_url: string;
}>();

type BatchItem = {
    ordinal: number;
    source_row: number;
    name: string | null;
    mobile: string | null;
    email: string | null;
    amount_minor: number;
    pay_code: string | null;
    claim_url: string | null;
    status: string;
    issued_at: string | null;
    failure_message: string | null;
};

type BatchPayload = {
    reference: string;
    status: string;
    item_count: number;
    valid_item_count: number;
    total_minor: number;
    expires_at: string | null;
    funded_at: string | null;
    issued_at: string | null;
    items: BatchItem[];
};

const batch = ref<BatchPayload | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const tokenKey = computed(() => `x-change.batch-token:${props.batch_reference}`);
const token = () => window.sessionStorage.getItem(tokenKey.value);

async function loadBatch(): Promise<void> {
    const accessToken = token();

    if (!accessToken) {
        error.value = 'This batch workspace is no longer available in this browser session.';
        loading.value = false;
        return;
    }

    try {
        const response = await fetch(props.batch_endpoint, {
            headers: {
                Accept: 'application/json',
                'X-Issuance-Batch-Token': accessToken,
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'The batch could not be loaded.');
        }

        batch.value = payload as BatchPayload;
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'The batch could not be loaded.';
    } finally {
        loading.value = false;
    }
}

function money(minor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(minor / 100);
}

function batchRecord(item: BatchItem): CockpitPayCodeExplorerRecord {
    const issued = item.pay_code !== null;
    const claimAction: CockpitPayCodeRowAction[] = item.claim_url === null
        ? []
        : [{ key: 'claim-url', label: 'Open claim URL', href: item.claim_url, enabled: true }];

    return {
        code: item.pay_code ?? `row-${item.ordinal}`,
        template: 'Batch Pay Code',
        purpose: item.failure_message,
        capability: {
            key: 'collection',
            label: 'Collection',
            voucherTypeLabel: 'Disbursable',
        },
        instructionBadges: [],
        amount: money(item.amount_minor),
        amountPresentation: {
            flowType: 'disbursable',
            label: 'Disbursable',
            amountMinor: item.amount_minor,
            targetAmountMinor: null,
            amount: money(item.amount_minor),
            targetAmount: null,
        },
        status: item.status,
        consumerStatus: null,
        collection: {},
        claimSummary: {
            claimed_at: null,
            claimed_by_label: null,
            claimed_mobile_masked: null,
            amount_minor: null,
            currency: 'PHP',
            location_label: null,
        },
        voucherStatus: item.status,
        operationalStatus: {
            key: item.status,
            label: item.status,
            tone: issued ? 'healthy' : 'warning',
            availabilityKey: issued ? 'available' : 'pending',
            availabilityLabel: issued ? 'Available' : 'Pending',
            settlementOutcome: issued ? 'issued' : 'pending',
            isTerminal: false,
            canClaim: issued,
            canRetryPayout: false,
        },
        party: {
            state: item.name ? 'named' : 'unidentified',
            label: 'Recipient',
            primary: item.name ?? 'Unnamed recipient',
            secondary: item.mobile ?? item.email,
            masked: false,
        },
        timing: {
            createdAt: null,
            startsAt: null,
            expiresAt: null,
            redeemedAt: null,
            terminalAt: item.issued_at,
        },
        terminalControl: {
            canExpire: false,
            canCancel: false,
            blockedReason: 'Batch Pay Codes are managed from the batch workspace.',
            status: item.status,
        },
        owner: 'This issuance batch',
        lastActivity: item.issued_at ?? 'Not issued',
        attention: item.failure_message === null
            ? null
            : { key: 'batch-item-failed', label: 'Needs attention', message: item.failure_message, tone: 'warning' },
        actions: claimAction,
        copyable: issued,
        claimUrl: item.claim_url,
    };
}

const records = computed(() => (batch.value?.items ?? []).map(batchRecord));
const issuedCount = computed(() => batch.value?.items.filter((item) => item.pay_code !== null).length ?? 0);

async function download(): Promise<void> {
    const accessToken = token();

    if (!accessToken) {
        error.value = 'This batch workspace is no longer available in this browser session.';
        return;
    }

    const response = await fetch(props.download_endpoint, {
        headers: {
            Accept: 'text/csv',
            'X-Issuance-Batch-Token': accessToken,
        },
    });

    if (!response.ok) {
        error.value = 'The batch CSV could not be downloaded.';
        return;
    }

    const link = document.createElement('a');
    link.href = URL.createObjectURL(await response.blob());
    link.download = `${props.batch_reference}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

onMounted(loadBatch);
</script>

<template>
    <div class="min-h-svh bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
        <Head :title="`Pay Codes · ${props.batch_reference}`" />
        <main class="mx-auto flex w-full max-w-[90rem] flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">Temporary distribution workspace</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">Batch Pay Codes</h1>
                    <p class="mt-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ props.batch_reference }}</p>
                    <p class="mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-300">This private workspace is limited to the Pay Codes generated by this batch.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="!batch" @click="download">Download CSV</button>
                    <Link :href="props.auto_generate_url" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-500 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Create another</Link>
                </div>
            </header>

            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">Loading this batch…</div>
            <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">{{ error }}</div>
            <template v-else-if="batch">
                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p><p class="mt-1 text-lg font-bold">{{ batch.status }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Issued</p><p class="mt-1 text-lg font-bold">{{ issuedCount }} / {{ batch.item_count }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pay Code value</p><p class="mt-1 text-lg font-bold">{{ money(batch.total_minor) }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rows</p><p class="mt-1 text-lg font-bold">{{ batch.item_count }}</p></div>
                </section>
                <CockpitPayCodeResultsTable :records="records" :actions="[]" />
            </template>
        </main>
    </div>
</template>
