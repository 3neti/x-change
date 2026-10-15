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
    recovery_available: boolean;
    batch_endpoint: string;
    download_endpoint: string;
    detail_url_template: string;
    recovery_request_endpoint: string;
    recovery_verify_endpoint: string;
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
    lifecycle_status?: string | null;
    redeemed_at?: string | null;
    claim_summary?: {
        status?: string | null;
        claimed_at?: string | null;
        amount_minor?: number | null;
        currency?: string | null;
        claimer_name?: string | null;
        claimer_mobile_masked?: string | null;
        settlement_mode?: string | null;
        settlement_rail?: string | null;
        provider?: string | null;
        bank_code?: string | null;
        account_number_masked?: string | null;
        provider_transaction_id?: string | null;
        payout_status?: string | null;
    };
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
const recoveryMobile = ref('');
const recoveryCode = ref('');
const recoveryChallenge = ref<string | null>(null);
const recoveryBusy = ref(false);
const recoveryMessage = ref<string | null>(null);
const recoveryError = ref<string | null>(null);
const recoveryOpen = ref(false);
const pageStartedAt = performance.now();

const tokenKey = computed(() => `x-change.batch-token:${props.batch_reference}`);
const token = () => window.sessionStorage.getItem(tokenKey.value);

async function loadBatch(): Promise<void> {
    const accessToken = token();

    try {
        const response = await fetch(props.batch_endpoint, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(accessToken ? { 'X-Issuance-Batch-Token': accessToken } : {}),
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
        recoveryOpen.value = true;
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

function clientTelemetry(): Record<string, number | string> {
    const deviceMemory = (navigator as Navigator & { deviceMemory?: number }).deviceMemory;
    const connection = (navigator as Navigator & {
        connection?: { effectiveType?: string };
    }).connection;

    return {
        ...(typeof deviceMemory === 'number' ? { device_memory_gb: deviceMemory } : {}),
        ...(typeof navigator.hardwareConcurrency === 'number' ? { hardware_concurrency: navigator.hardwareConcurrency } : {}),
        viewport_width: window.innerWidth,
        viewport_height: window.innerHeight,
        ...(connection?.effectiveType ? { connection_type: connection.effectiveType } : {}),
        page_load_ms: Math.round(performance.now() - pageStartedAt),
    };
}

function batchRecord(item: BatchItem): CockpitPayCodeExplorerRecord {
    const issued = item.pay_code !== null;
    const lifecycleStatus = item.lifecycle_status ?? item.status;
    const detailAction: CockpitPayCodeRowAction[] = item.pay_code === null
        ? []
        : [{
            key: 'detail',
            label: 'View details',
            href: props.detail_url_template.replace('__CODE__', encodeURIComponent(item.pay_code)),
            enabled: true,
        }];

    return {
        code: item.pay_code ?? `row-${item.ordinal}`,
        template: 'Batch Pay Code',
        purpose: item.failure_message,
        capability: {
            key: 'disbursement',
            label: 'Disbursement',
            voucherTypeLabel: 'Redeemable',
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
        status: lifecycleStatus,
        consumerStatus: null,
        collection: {},
        claimSummary: {
            claimed_at: item.claim_summary?.claimed_at ?? item.redeemed_at ?? null,
            claimed_by_label: item.claim_summary?.claimer_name ?? item.claim_summary?.claimer_mobile_masked ?? null,
            claimed_mobile_masked: item.claim_summary?.claimer_mobile_masked ?? null,
            amount_minor: item.claim_summary?.amount_minor ?? null,
            currency: item.claim_summary?.currency ?? 'PHP',
            location_label: item.claim_summary?.settlement_rail ?? item.claim_summary?.provider ?? item.claim_summary?.bank_code ?? null,
        },
        voucherStatus: lifecycleStatus,
        operationalStatus: {
            key: lifecycleStatus,
            label: lifecycleStatus,
            tone: lifecycleStatus === 'payout_rejected' ? 'critical' : lifecycleStatus === 'issued' ? 'healthy' : 'positive',
            availabilityKey: lifecycleStatus === 'issued' ? 'available' : 'closed',
            availabilityLabel: lifecycleStatus === 'issued' ? 'Available' : 'Closed',
            settlementOutcome: lifecycleStatus === 'issued' ? 'issued' : lifecycleStatus,
            isTerminal: lifecycleStatus !== 'issued' && lifecycleStatus !== 'partially_claimed',
            canClaim: lifecycleStatus === 'issued' || lifecycleStatus === 'partially_claimed',
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
            redeemedAt: item.redeemed_at ?? item.claim_summary?.claimed_at ?? null,
            terminalAt: item.redeemed_at ?? item.claim_summary?.claimed_at ?? item.issued_at,
        },
        terminalControl: {
            canExpire: false,
            canCancel: false,
            blockedReason: 'Batch Pay Codes are managed from the batch workspace.',
            status: item.status,
        },
        owner: 'This issuance batch',
        lastActivity: item.redeemed_at ?? item.claim_summary?.claimed_at ?? item.issued_at ?? 'Not issued',
        attention: item.failure_message === null
            ? null
            : { key: 'batch-item-failed', label: 'Needs attention', message: item.failure_message, tone: 'warning' },
        actions: detailAction,
        copyable: issued,
        claimUrl: item.claim_url,
    };
}

const records = computed(() => (batch.value?.items ?? []).map(batchRecord));
const issuedCount = computed(() => batch.value?.items.filter((item) => item.pay_code !== null).length ?? 0);

async function download(): Promise<void> {
    const accessToken = token();

    const response = await fetch(props.download_endpoint, {
        credentials: 'same-origin',
        headers: {
            Accept: 'text/csv',
            ...(accessToken ? { 'X-Issuance-Batch-Token': accessToken } : {}),
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

async function requestRecoveryCode(): Promise<void> {
    recoveryBusy.value = true;
    recoveryError.value = null;
    try {
        const response = await fetch(props.recovery_request_endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ mobile: recoveryMobile.value, telemetry: clientTelemetry() }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message ?? 'Recovery could not be started.');
        recoveryChallenge.value = payload.challenge_reference;
        recoveryMessage.value = payload.message ?? 'Enter the recovery code sent by SMS.';
    } catch (exception) {
        recoveryError.value = exception instanceof Error ? exception.message : 'Recovery could not be started.';
    } finally {
        recoveryBusy.value = false;
    }
}

async function verifyRecoveryCode(): Promise<void> {
    if (!recoveryChallenge.value) return;
    recoveryBusy.value = true;
    recoveryError.value = null;
    try {
        const response = await fetch(props.recovery_verify_endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                challenge_reference: recoveryChallenge.value,
                code: recoveryCode.value,
                telemetry: clientTelemetry(),
            }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message ?? 'The recovery code could not be verified.');
        window.location.href = payload.workspace_url;
    } catch (exception) {
        recoveryError.value = exception instanceof Error ? exception.message : 'The recovery code could not be verified.';
    } finally {
        recoveryBusy.value = false;
    }
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

            <section v-if="props.recovery_available" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm dark:border-amber-900 dark:bg-amber-950/20" data-testid="batch-workspace-recovery">
                <button type="button" class="flex w-full items-center justify-between gap-3 text-left" data-testid="batch-workspace-recovery-toggle" @click="recoveryOpen = !recoveryOpen">
                    <span>
                        <span class="block text-sm font-bold text-amber-950 dark:text-amber-100">Need to recover this workspace?</span>
                        <span class="mt-1 block text-xs text-amber-900/80 dark:text-amber-200/80">Use the mobile number recorded when this batch was created. We will send a one-time recovery code only after you request it.</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-amber-800 dark:text-amber-200">{{ recoveryOpen ? 'Hide' : 'Recover' }}</span>
                </button>
                <div v-if="recoveryOpen" class="mt-4 max-w-md">
                    <form v-if="!recoveryChallenge" class="space-y-2" @submit.prevent="requestRecoveryCode">
                        <input v-model="recoveryMobile" type="tel" inputmode="tel" autocomplete="tel" required placeholder="09XXXXXXXXX" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950" />
                        <button type="submit" :disabled="recoveryBusy" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ recoveryBusy ? 'Sending…' : 'Send recovery code' }}</button>
                    </form>
                    <form v-else class="space-y-2" @submit.prevent="verifyRecoveryCode">
                        <p class="text-xs text-amber-950 dark:text-amber-100">{{ recoveryMessage }}</p>
                        <input v-model="recoveryCode" type="text" inputmode="numeric" autocomplete="one-time-code" required placeholder="Recovery code" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950" />
                        <button type="submit" :disabled="recoveryBusy" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ recoveryBusy ? 'Checking…' : 'Recover workspace' }}</button>
                    </form>
                    <p v-if="recoveryError" class="mt-2 text-xs font-semibold text-rose-700">{{ recoveryError }}</p>
                </div>
            </section>

            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">Loading this batch…</div>
            <div v-else-if="error" class="space-y-5 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
                <p>{{ error }}</p>
            </div>
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
