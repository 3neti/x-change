<script setup lang="ts">
import { useForm, usePoll } from '@inertiajs/vue3';
import { approve as approveReconciliation } from '@/routes/x-change/cockpit/funding/reconciliations';
import { show as showFundingInstructions } from '@/routes/x-change/cockpit/funding/intents/instructions';
import { store as storeFundingIntent } from '@/routes/x-change/cockpit/funding/intents';
import { store as storeVerificationCheck } from '@/routes/x-change/cockpit/funding/intents/verification-checks';
import { store as openStandingFundingAddressRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank';
import { store as checkStandingFundingHistoryRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/history-checks';
import { approve as approveStandingFundingReceiptRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/receipts';
import { store as runQrPhFundingSimulationRoute } from '@/routes/x-change/cockpit/funding/scenarios/qrph';
import { store as storeReconciliationRequest } from '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests';
import { computed, ref, watch } from 'vue';
import CockpitManualCopyButton from '../components/CockpitManualCopyButton.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitFundingPageProps,
    CockpitFundingInstruction,
    CockpitQrPhFundingSimulationResult,
    CockpitStandingFundingAddress,
    CockpitStandingFundingReceipt,
} from '../types';

const props = defineProps<CockpitFundingPageProps>();
const currentInstruction = ref<CockpitFundingInstruction | null>(
    props.funding_instruction ?? null,
);
const amount = ref('');
const amountError = ref<string | null>(null);
const activeReconciliationCase = ref<string | null>(null);
const activeApproval = ref<string | null>(null);
const activeVerificationCheck = ref<string | null>(null);
const activeInstructionRequest = ref<string | null>(null);
const instructionError = ref<string | null>(null);
const simulationRunning = ref(false);
const simulationError = ref<string | null>(null);
const simulationResult = ref<CockpitQrPhFundingSimulationResult | null>(null);
const standingAddress = ref<CockpitStandingFundingAddress | null>(null);
const standingReceipts = ref<CockpitStandingFundingReceipt[]>([]);
const standingAddressLoading = ref(false);
const standingHistoryLoading = ref(false);
const standingAddressError = ref<string | null>(null);
const standingHistoryCheckedAt = ref<string | null>(null);
const activeStandingReceiptApproval = ref<string | null>(null);
const standingActionNotice = ref<string | null>(null);
const activeSimulationStepIndex = ref(0);
const activeSimulationStep = computed(
    () =>
        simulationResult.value?.steps[activeSimulationStepIndex.value] ?? null,
);
const availableFundingProviders = computed(() =>
    props.funding_read_model.providers.filter(
        (provider) => provider.status === 'available',
    ),
);
const hasOpenFundingIntents = computed(() =>
    props.funding_read_model.intents.some((intent) =>
        ['awaiting_funds', 'evidence_received', 'verifying'].includes(
            intent.status,
        ),
    ),
);
const { start: startFundingPoll, stop: stopFundingPoll } = usePoll(
    Math.max(1000, props.funding_poll_interval ?? 5000),
    {
        only: ['funding_read_model', 'funding_notice'],
    },
    {
        autoStart: hasOpenFundingIntents.value,
        mode: 'rest',
    },
);
const form = useForm({
    provider: availableFundingProviders.value[0]?.code ?? '',
    amount_minor: 0,
    currency: 'PHP',
    idempotency_key: newIdempotencyKey(),
});
const selectedFundingProvider = computed(() =>
    props.funding_read_model.providers.find(
        (provider) => provider.code === form.provider,
    ),
);
const simulationIntentSelected = computed(
    () => selectedFundingProvider.value?.simulation_only === true,
);
const reconciliationForm = useForm({
    action: '',
});
const approvalForm = useForm({});
const verificationForm = useForm({});
const clientAmountError = computed(() => {
    if (amount.value === '' || amountToMinor(amount.value) !== null) {
        return null;
    }

    return 'Enter an amount greater than zero with no more than two decimal places.';
});

watch(hasOpenFundingIntents, (hasOpenIntents) => {
    if (hasOpenIntents) {
        startFundingPoll();

        return;
    }

    stopFundingPoll();
});

watch(
    () => props.funding_instruction,
    (instruction) => {
        if (instruction) {
            currentInstruction.value = instruction;
        }
    },
);

const summaryCards = computed(() => [
    {
        key: 'awaiting',
        label: 'Awaiting Funds',
        value: String(props.funding_read_model.summary.awaiting_funds),
        helper: 'Open Funding Intents waiting for authoritative settlement.',
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        key: 'settled',
        label: 'Settled Funding',
        value: props.funding_read_model.summary.settled_funding,
        helper: 'Verified net funding posted to this Account.',
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'suspense',
        label: 'Open Suspense',
        value: String(props.funding_read_model.summary.open_suspense),
        helper: 'Mismatched or ambiguous evidence requiring review.',
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        key: 'recovery',
        label: 'Recovery Outstanding',
        value: props.funding_read_model.summary.recovery_outstanding,
        helper: 'Reversed funding still held against future Issuance Capacity.',
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const safeguards = [
    'Every credit is bound to an exact Funding Intent or an immutable Account Funding Address.',
    'A webhook stores evidence and wakes verification; it never credits an Account.',
    'Settlement is re-queried from the provider before exact net funding is posted.',
    'Mismatches enter suspense; operators cannot type an arbitrary credit amount.',
    'Reconciliation and compensation require separate maker and checker identities.',
];

function displayLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}

function newIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `cockpit-funding-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function amountToMinor(value: string): number | null {
    const normalized = value.trim();

    if (!/^\d{1,10}(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, decimal = ''] = normalized.split('.');
    const amountMinor = Number(whole) * 100 + Number(decimal.padEnd(2, '0'));

    return Number.isSafeInteger(amountMinor) && amountMinor > 0
        ? amountMinor
        : null;
}

function submitFundingIntent(): void {
    form.clearErrors();
    amountError.value = null;

    const amountMinor = amountToMinor(amount.value);

    if (amountMinor === null) {
        amountError.value =
            'Enter an amount greater than zero with no more than two decimal places.';

        return;
    }

    form.amount_minor = amountMinor;
    form.post(storeFundingIntent(), {
        preserveScroll: true,
        onSuccess: () => {
            amount.value = '';
            form.amount_minor = 0;
            form.idempotency_key = newIdempotencyKey();
        },
    });
}

function requestReconciliation(caseReference: string, action: string): void {
    activeReconciliationCase.value = caseReference;
    reconciliationForm.action = action;
    reconciliationForm.post(storeReconciliationRequest(caseReference), {
        preserveScroll: true,
        onFinish: () => {
            activeReconciliationCase.value = null;
        },
    });
}

function approveFundingReconciliation(reference: string): void {
    activeApproval.value = reference;
    approvalForm.post(approveReconciliation(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeApproval.value = null;
        },
    });
}

function checkNetBank(reference: string): void {
    if (activeVerificationCheck.value !== null) {
        return;
    }

    activeVerificationCheck.value = reference;

    verificationForm.post(storeVerificationCheck(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeVerificationCheck.value = null;
        },
    });
}

async function reopenFundingInstructions(reference: string): Promise<void> {
    if (activeInstructionRequest.value !== null) {
        return;
    }

    activeInstructionRequest.value = reference;
    instructionError.value = null;
    const route = showFundingInstructions(reference);

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            typeof body.instruction !== 'object' ||
            body.instruction === null
        ) {
            instructionError.value =
                response.status === 410
                    ? 'These one-time instructions have expired.'
                    : 'The one-time instructions could not be reopened.';

            return;
        }

        currentInstruction.value =
            body.instruction as CockpitFundingInstruction;
    } catch {
        instructionError.value =
            'The one-time instructions could not reach the Cockpit service.';
    } finally {
        activeInstructionRequest.value = null;
    }
}

function reconciliationActionLabel(action: string): string {
    return (
        {
            retry_verification: 'Request verification retry',
            match_verified_observation: 'Request exact evidence match',
            compensate_verified_posting: 'Request verified posting',
        }[action] ?? displayLabel(action)
    );
}

async function runQrPhFundingSimulation(): Promise<void> {
    if (
        simulationRunning.value ||
        props.funding_simulation?.enabled !== true ||
        props.funding_simulation.mobile_ready !== true
    ) {
        return;
    }

    simulationRunning.value = true;
    simulationError.value = null;
    const route = runQrPhFundingSimulationRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = await safeJson(response);

        if (!response.ok) {
            simulationError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'The QR Ph simulation could not complete safely.';

            return;
        }

        if (
            body.schema !== 'x-change.lifecycle.qrph-funding-simulation.v1' ||
            !Array.isArray(body.steps)
        ) {
            simulationError.value =
                'The QR Ph simulation returned an unexpected response.';

            return;
        }

        simulationResult.value = body as CockpitQrPhFundingSimulationResult;
        activeSimulationStepIndex.value = 0;
    } catch {
        simulationError.value =
            'The QR Ph simulation could not reach the Cockpit service.';
    } finally {
        simulationRunning.value = false;
    }
}

async function openStandingFundingAddress(): Promise<void> {
    if (
        standingAddressLoading.value ||
        props.standing_funding_address?.available !== true
    ) {
        return;
    }

    standingAddressLoading.value = true;
    standingAddressError.value = null;
    const route = openStandingFundingAddressRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify({
                confirm_account_funding_address: true,
            }),
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            body.schema !== 'x-change.cockpit.standing-funding-address.v1' ||
            typeof body.address !== 'object' ||
            body.address === null
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'NetBank could not open the Account Funding Address.';

            return;
        }

        standingAddress.value = body.address as CockpitStandingFundingAddress;
        standingReceipts.value = [];
        standingHistoryCheckedAt.value = null;
    } catch {
        standingAddressError.value =
            'The Account Funding Address could not reach NetBank.';
    } finally {
        standingAddressLoading.value = false;
    }
}

async function checkStandingFundingHistory(): Promise<void> {
    if (standingHistoryLoading.value || standingAddress.value === null) {
        return;
    }

    standingHistoryLoading.value = true;
    standingAddressError.value = null;
    const route = checkStandingFundingHistoryRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify({
                confirm_account_funding_address: true,
            }),
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            body.schema !== 'x-change.cockpit.standing-funding-history.v1' ||
            !Array.isArray(body.observations)
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'NetBank history could not be checked.';

            return;
        }

        standingReceipts.value =
            body.observations as CockpitStandingFundingReceipt[];
        standingHistoryCheckedAt.value =
            typeof body.checked_at === 'string' ? body.checked_at : null;
        standingActionNotice.value =
            body.balance_changed === true
                ? 'New NetBank funding was applied to Internal Balance exactly once.'
                : body.observations.length > 0
                  ? 'NetBank history refreshed. Previously applied receipts were not applied again.'
                  : null;
    } catch {
        standingAddressError.value =
            'The NetBank history check could not reach the provider.';
    } finally {
        standingHistoryLoading.value = false;
    }
}

async function approveStandingFundingReceipt(
    receipt: CockpitStandingFundingReceipt,
): Promise<void> {
    if (
        activeStandingReceiptApproval.value !== null ||
        !receipt.approval_reference
    ) {
        return;
    }

    const displayReference = receipt.reference;

    activeStandingReceiptApproval.value = displayReference;
    standingAddressError.value = null;
    standingActionNotice.value = null;
    const route = approveStandingFundingReceiptRoute(
        receipt.approval_reference,
    );

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            body.schema !==
                'x-change.cockpit.account-funding-receipt-approval.v1'
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'The verified funding receipt could not be approved.';

            return;
        }

        standingReceipts.value = standingReceipts.value.map((receipt) =>
            receipt.reference === displayReference
                ? {
                      ...receipt,
                      status: 'settled',
                      applied: true,
                      applied_amount_minor: receipt.net_amount_minor,
                      applied_amount: receipt.net_amount,
                      applied_at:
                          typeof body.receipt === 'object' &&
                          body.receipt !== null &&
                          typeof (body.receipt as Record<string, unknown>)
                              .settled_at === 'string'
                              ? (body.receipt as Record<string, string>)
                                    .settled_at
                              : receipt.applied_at,
                      provisional: false,
                      can_approve: false,
                      approval_reference: null,
                  }
                : receipt,
        );
        standingActionNotice.value =
            typeof body.message === 'string'
                ? body.message
                : 'Verified funding was credited to the Account.';
    } catch {
        standingAddressError.value =
            'The funding approval could not reach the Cockpit service.';
    } finally {
        activeStandingReceiptApproval.value = null;
    }
}

function hideStandingFundingAddress(): void {
    standingAddress.value = null;
    standingReceipts.value = [];
    standingHistoryCheckedAt.value = null;
    standingAddressError.value = null;
    standingActionNotice.value = null;
}

function formatMinor(value?: number | null, currency = 'PHP'): string {
    if (value === null || value === undefined) {
        return 'No limit';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const body = await response.json();

        return typeof body === 'object' && body !== null
            ? (body as Record<string, unknown>)
            : {};
    } catch {
        return {};
    }
}
</script>

<template>
    <CockpitLayout
        active-navigation="funding"
        :cockpit-header-read-model="cockpit_header_read_model"
    >
        <div
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-funding-page"
        >
            <div
                v-if="funding_notice"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                role="status"
            >
                {{ funding_notice }}
            </div>
            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 text-white shadow-sm dark:border-slate-800"
            >
                <div
                    class="grid gap-6 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-6"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.22em] text-sky-300 uppercase"
                            >
                                Funding control
                            </p>
                            <span
                                class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide text-slate-200 uppercase"
                            >
                                provider verified
                            </span>
                        </div>
                        <h1
                            class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Account Funding
                        </h1>
                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-slate-300"
                        >
                            Request funding instructions and monitor
                            authoritative settlement. There is no manual “add
                            funds” control: only verified bank or EMI evidence
                            can increase the Account balance.
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-4"
                    >
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-emerald-300 uppercase"
                        >
                            Control posture
                        </p>
                        <p class="mt-1 text-sm font-semibold text-white">
                            Webhook evidence ≠ Account credit
                        </p>
                        <p class="mt-1 text-xs leading-5 text-emerald-100/80">
                            The provider is queried independently before
                            Inventory recognition and Account posting occur
                            atomically.
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-if="standing_funding_address"
                class="overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm dark:border-sky-950 dark:bg-slate-900"
                data-testid="cockpit-standing-funding-address"
            >
                <div
                    class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Account Funding Address
                            </p>
                            <span
                                class="rounded-full bg-sky-100 px-2 py-1 text-[0.65rem] font-semibold text-sky-800 uppercase dark:bg-sky-950 dark:text-sky-200"
                            >
                                {{
                                    standing_funding_address.recognition_mode.replaceAll(
                                        '_',
                                        ' ',
                                    )
                                }}
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                            >
                                Purpose bound
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                data-testid="standing-funding-address-scheme"
                            >
                                {{ standing_funding_address.scheme_label }}
                            </span>
                        </div>
                        <h2 class="mt-1.5 text-lg font-semibold">
                            Stable NetBank QR Ph address
                        </h2>
                        <p
                            class="mt-1 max-w-4xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            This exact VCA is permanently classified as
                            <strong>account funding</strong> for this Account. A
                            payer chooses the amount; payer mobile, amount,
                            timing, and merchant text never decide where the
                            credit goes.
                        </p>
                        <p
                            v-if="standing_funding_address.scheme_warning"
                            class="mt-2 max-w-4xl text-xs leading-5 text-amber-700 dark:text-amber-300"
                            data-testid="standing-funding-address-scheme-warning"
                        >
                            {{ standing_funding_address.scheme_warning }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <button
                            v-if="standingAddress === null"
                            type="button"
                            class="h-10 rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                standingAddressLoading ||
                                standing_funding_address.available !== true
                            "
                            data-testid="open-standing-funding-address"
                            @click="openStandingFundingAddress"
                        >
                            {{
                                standingAddressLoading
                                    ? 'Contacting NetBank…'
                                    : standing_funding_address.available
                                      ? standing_funding_address.exists
                                          ? 'Open Account Funding QR'
                                          : 'Create Account Funding QR'
                                      : 'Account Funding Address unavailable'
                            }}
                        </button>
                        <template v-else>
                            <button
                                type="button"
                                class="h-10 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-sky-400 dark:text-slate-950 dark:hover:bg-sky-300"
                                :disabled="standingHistoryLoading"
                                data-testid="check-standing-funding-history"
                                @click="checkStandingFundingHistory"
                            >
                                {{
                                    standingHistoryLoading
                                        ? 'Checking NetBank…'
                                        : 'Check NetBank'
                                }}
                            </button>
                            <button
                                type="button"
                                class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="hide-standing-funding-address"
                                @click="hideStandingFundingAddress"
                            >
                                Hide sensitive QR
                            </button>
                        </template>
                    </div>
                </div>

                <div
                    v-if="standingAddress"
                    class="border-t border-sky-100 bg-sky-50/50 p-5 dark:border-sky-950 dark:bg-sky-950/10"
                >
                    <div
                        class="grid gap-5 md:grid-cols-[12rem_minmax(0,1fr)] md:items-start"
                    >
                        <div
                            class="mx-auto rounded-xl border border-sky-200 bg-white p-2 shadow-sm md:mx-0 dark:border-sky-900"
                        >
                            <img
                                :src="standingAddress.qr_code"
                                alt="Account Funding Address QR Ph code"
                                class="size-44 object-contain"
                                data-testid="standing-funding-address-qr"
                            />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                NetBank VCA
                            </p>
                            <p
                                class="mt-1 font-mono text-base font-semibold break-all"
                                data-testid="standing-funding-address-value"
                            >
                                {{ standingAddress.funding_address }}
                            </p>
                            <div class="mt-3">
                                <CockpitManualCopyButton
                                    :value="standingAddress.funding_address"
                                    label="Copy receiving address"
                                    helper="Browser-local copy only."
                                />
                            </div>
                            <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-3">
                                <div
                                    class="rounded-lg bg-white px-3 py-2 dark:bg-slate-950"
                                >
                                    <dt class="text-slate-500">Amount</dt>
                                    <dd class="mt-0.5 font-semibold">
                                        Payer enters amount
                                    </dd>
                                </div>
                                <div
                                    class="rounded-lg bg-white px-3 py-2 dark:bg-slate-950"
                                >
                                    <dt class="text-slate-500">Recognition</dt>
                                    <dd class="mt-0.5 font-semibold">
                                        {{
                                            displayLabel(
                                                standingAddress.recognition_mode,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="rounded-lg bg-white px-3 py-2 dark:bg-slate-950"
                                >
                                    <dt class="text-slate-500">
                                        Per-transfer range
                                    </dt>
                                    <dd class="mt-0.5 font-semibold">
                                        {{
                                            formatMinor(
                                                standingAddress.minimum_amount_minor,
                                                standingAddress.currency,
                                            )
                                        }}
                                        –
                                        {{
                                            formatMinor(
                                                standingAddress.maximum_amount_minor,
                                                standingAddress.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <p
                                class="mt-4 text-xs leading-5 text-sky-800 dark:text-sky-200"
                            >
                                Scanning the QR does not itself change the
                                Account. NetBank transaction history is the
                                authority. Observe-only records a receipt;
                                supervised mode waits for approval; automatic
                                mode credits only after every destination,
                                currency, status, and limit check passes.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="standingActionNotice"
                        class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                        role="status"
                    >
                        {{ standingActionNotice }}
                    </div>

                    <div
                        class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800"
                        >
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Account Funding Receipts
                                </h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Sanitized classification and recognition
                                    status. Payer identity and raw provider data
                                    stay hidden.
                                </p>
                            </div>
                            <span class="text-xs text-slate-500">
                                {{
                                    standingHistoryCheckedAt
                                        ? `Checked ${displayTime(
                                              standingHistoryCheckedAt,
                                          )}`
                                        : 'Not checked yet'
                                }}
                            </span>
                        </div>
                        <div
                            v-if="standingReceipts.length"
                            class="overflow-x-auto"
                        >
                            <table
                                class="w-full min-w-[52rem] text-left text-sm"
                            >
                                <thead
                                    class="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-900"
                                >
                                    <tr>
                                        <th class="px-4 py-2.5">Reference</th>
                                        <th class="px-4 py-2.5">Amount</th>
                                        <th class="px-4 py-2.5">NetBank</th>
                                        <th class="px-4 py-2.5">Applied</th>
                                        <th class="px-4 py-2.5">Observed</th>
                                        <th class="px-4 py-2.5 text-right">
                                            Control
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="receipt in standingReceipts"
                                        :key="receipt.reference"
                                        class="border-t border-slate-100 dark:border-slate-800"
                                    >
                                        <td
                                            class="px-4 py-3 font-mono text-xs font-semibold"
                                        >
                                            {{ receipt.reference }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold">
                                            {{ receipt.gross_amount }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                displayLabel(
                                                    receipt.provider_status,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div
                                                v-if="receipt.applied"
                                                class="flex flex-col gap-0.5"
                                            >
                                                <span
                                                    class="font-semibold text-emerald-700 dark:text-emerald-300"
                                                >
                                                    Yes ·
                                                    {{ receipt.applied_amount }}
                                                </span>
                                                <span
                                                    v-if="receipt.provisional"
                                                    class="text-[0.7rem] font-medium text-amber-700 dark:text-amber-300"
                                                >
                                                    Provisional provider status
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-[0.7rem] text-slate-500"
                                                >
                                                    {{
                                                        displayTime(
                                                            receipt.applied_at,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                v-else
                                                class="flex flex-col gap-0.5"
                                            >
                                                <span
                                                    class="font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    No
                                                </span>
                                                <span
                                                    class="text-[0.7rem] text-slate-500"
                                                >
                                                    {{
                                                        displayLabel(
                                                            receipt.status,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500">
                                            {{
                                                displayTime(receipt.occurred_at)
                                            }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                v-if="receipt.can_approve"
                                                type="button"
                                                class="h-8 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    activeStandingReceiptApproval !==
                                                    null
                                                "
                                                data-testid="approve-standing-funding-receipt"
                                                @click="
                                                    approveStandingFundingReceipt(
                                                        receipt,
                                                    )
                                                "
                                            >
                                                {{
                                                    activeStandingReceiptApproval ===
                                                    receipt.reference
                                                        ? 'Posting…'
                                                        : 'Approve verified credit'
                                                }}
                                            </button>
                                            <span
                                                v-else
                                                class="text-xs text-slate-400"
                                            >
                                                —
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="px-4 py-5 text-sm text-slate-500"
                            data-testid="standing-funding-history-empty"
                        >
                            {{
                                standingHistoryCheckedAt
                                    ? 'NetBank returned no incoming transactions for this address in the configured lookback window.'
                                    : 'Check NetBank after a human scans and pays the QR.'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="standingAddressError"
                    class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-200"
                    role="alert"
                >
                    {{ standingAddressError }}
                </div>
            </section>

            <section
                v-if="funding_simulation"
                class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-950 dark:bg-slate-900"
                data-testid="cockpit-qrph-funding-simulation"
            >
                <div
                    class="grid gap-5 p-5 md:grid-cols-[10rem_minmax(0,1fr)_auto] md:items-center"
                >
                    <div
                        class="mx-auto rounded-xl border border-slate-200 bg-white p-2 shadow-sm md:mx-0"
                    >
                        <img
                            :src="funding_simulation.qr_code"
                            alt="Illustrative QR Ph funding simulation code"
                            class="size-36"
                        />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-violet-700 uppercase dark:text-violet-300"
                            >
                                QR Ph lifecycle lab
                            </p>
                            <span
                                class="rounded-full bg-violet-50 px-2 py-1 text-[0.65rem] font-semibold text-violet-700 uppercase dark:bg-violet-950 dark:text-violet-200"
                            >
                                Rollback only
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                            >
                                No monetary value
                            </span>
                        </div>
                        <h2 class="mt-1.5 text-lg font-semibold">
                            Simulate a {{ funding_simulation.amount }} QR Ph
                            funding payment
                        </h2>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            Appreciate how a verified payer mobile resolves the
                            Account, how signed evidence is independently
                            checked, and why an identical callback cannot credit
                            twice. Every database and balance change is rolled
                            back.
                        </p>
                    </div>
                    <div class="md:text-right">
                        <button
                            type="button"
                            :disabled="
                                simulationRunning ||
                                funding_simulation.enabled !== true ||
                                funding_simulation.mobile_ready !== true
                            "
                            class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="run-qrph-funding-simulation"
                            @click="runQrPhFundingSimulation"
                        >
                            {{
                                simulationRunning
                                    ? 'Simulating safely…'
                                    : funding_simulation.enabled !== true
                                      ? 'Unavailable here'
                                      : funding_simulation.mobile_ready !== true
                                        ? 'Verified mobile required'
                                        : 'Simulate scan and payment'
                            }}
                        </button>
                        <p class="mt-2 text-xs text-slate-500">
                            0 provider calls · 0 retained changes
                        </p>
                    </div>
                </div>

                <div
                    v-if="simulationError"
                    class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-200"
                    role="alert"
                >
                    {{ simulationError }}
                </div>

                <div
                    v-if="simulationResult && activeSimulationStep"
                    class="border-t border-violet-100 bg-slate-50/70 p-4 sm:p-5 dark:border-violet-950 dark:bg-slate-950/40"
                    data-testid="qrph-funding-simulation-stepper"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div
                            class="flex flex-wrap items-center gap-1.5"
                            aria-label="QR Ph simulation steps"
                        >
                            <button
                                v-for="(step, index) in simulationResult.steps"
                                :key="step.key"
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition"
                                :class="
                                    index === activeSimulationStepIndex
                                        ? 'bg-violet-600 text-white'
                                        : 'bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700'
                                "
                                :aria-label="`Open step ${index + 1}: ${step.label}`"
                                @click="activeSimulationStepIndex = index"
                            >
                                {{ index + 1 }}
                            </button>
                        </div>
                        <p class="text-xs font-medium text-slate-500">
                            Step {{ activeSimulationStepIndex + 1 }} of
                            {{ simulationResult.steps.length }}
                        </p>
                    </div>

                    <article
                        class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <h3 class="text-base font-semibold">
                                {{ activeSimulationStep.label }}
                            </h3>
                            <span
                                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                            >
                                {{ displayLabel(activeSimulationStep.outcome) }}
                            </span>
                        </div>
                        <dl
                            class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="fact in activeSimulationStep.facts"
                                :key="fact.label"
                                class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt class="text-[0.68rem] text-slate-500">
                                    {{ fact.label }}
                                </dt>
                                <dd class="mt-0.5 text-xs font-semibold">
                                    {{ fact.value }}
                                </dd>
                            </div>
                        </dl>
                    </article>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p
                            class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Rollback confirmed · one simulated credit · replay
                            no-op · nothing persisted
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="activeSimulationStepIndex === 0"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950"
                                @click="
                                    activeSimulationStepIndex = Math.max(
                                        0,
                                        activeSimulationStepIndex - 1,
                                    )
                                "
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-violet-400 dark:text-slate-950"
                                @click="
                                    activeSimulationStepIndex =
                                        activeSimulationStepIndex <
                                        simulationResult.steps.length - 1
                                            ? activeSimulationStepIndex + 1
                                            : 0
                                "
                            >
                                {{
                                    activeSimulationStepIndex <
                                    simulationResult.steps.length - 1
                                        ? 'Next'
                                        : 'Restart'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
            >
                <form
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-funding-intent-form"
                    @submit.prevent="submitFundingIntent"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                    >
                        Controlled intake
                    </p>
                    <h2 class="mt-1 text-lg font-semibold">
                        Create Funding Intent
                    </h2>
                    <p
                        class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"
                    >
                        {{
                            simulationIntentSelected
                                ? 'This creates exact local simulation instructions with zero provider calls.'
                                : 'This creates exact provider instructions.'
                        }}
                        It does not change Internal Balance or Issuance
                        Capacity.
                    </p>

                    <div
                        class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
                    >
                        <label class="block">
                            <span
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >Provider</span
                            >
                            <select
                                v-model="form.provider"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm transition outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:bg-slate-950"
                                :disabled="form.processing"
                                data-testid="cockpit-funding-provider"
                            >
                                <option
                                    v-if="
                                        availableFundingProviders.length === 0
                                    "
                                    disabled
                                    value=""
                                >
                                    No funding provider enabled
                                </option>
                                <option
                                    v-for="provider in funding_read_model.providers"
                                    :key="provider.code"
                                    :value="provider.code"
                                    :disabled="provider.status !== 'available'"
                                >
                                    {{ provider.label }} ·
                                    {{
                                        displayLabel(
                                            provider.destination_mode ??
                                                'shared',
                                        )
                                    }}
                                    {{
                                        provider.status === 'available'
                                            ? ''
                                            : ` (${provider.status})`
                                    }}
                                </option>
                            </select>
                            <span
                                v-if="form.errors.provider"
                                class="mt-1 block text-xs text-rose-600"
                                >{{ form.errors.provider }}</span
                            >
                            <span
                                v-else-if="simulationIntentSelected"
                                class="mt-1 block text-xs leading-5 text-emerald-700 dark:text-emerald-300"
                            >
                                Local happy path: creates a real Funding Intent
                                in x-change without contacting a bank or EMI.
                            </span>
                            <span
                                v-else-if="
                                    availableFundingProviders.length === 0
                                "
                                class="mt-1 block text-xs leading-5 text-amber-700 dark:text-amber-300"
                            >
                                NetBank and Paynamics are installed, but Funding
                                Intake stays locked until a provider is
                                explicitly enabled.
                            </span>
                        </label>

                        <label class="block">
                            <span
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >Exact amount</span
                            >
                            <div
                                class="mt-1.5 flex rounded-lg border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/20 dark:border-slate-700 dark:bg-slate-950"
                            >
                                <span
                                    class="flex items-center border-r border-slate-200 px-3 text-sm font-semibold text-slate-500 dark:border-slate-700"
                                    >PHP</span
                                >
                                <input
                                    v-model="amount"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="0.00"
                                    class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm outline-none"
                                    :disabled="form.processing"
                                    data-testid="cockpit-funding-amount"
                                />
                            </div>
                            <span
                                v-if="
                                    amountError ??
                                    clientAmountError ??
                                    form.errors.amount_minor
                                "
                                class="mt-1 block text-xs text-rose-600"
                                >{{
                                    amountError ??
                                    clientAmountError ??
                                    form.errors.amount_minor
                                }}</span
                            >
                        </label>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                form.processing ||
                                availableFundingProviders.length === 0
                            "
                            data-testid="cockpit-funding-submit"
                        >
                            {{
                                form.processing
                                    ? 'Creating instructions…'
                                    : simulationIntentSelected
                                      ? 'Create simulated funding instructions'
                                      : 'Create funding instructions'
                            }}
                        </button>
                        <p class="text-xs text-slate-500">
                            No balance mutation occurs on submit.
                        </p>
                    </div>
                </form>

                <article
                    class="rounded-xl border p-5 shadow-sm"
                    :class="
                        currentInstruction
                            ? 'border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20'
                            : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'
                    "
                    data-testid="cockpit-funding-instruction"
                >
                    <div v-if="currentInstruction">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                                >
                                    One-time instructions
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    Transfer exactly
                                    {{ currentInstruction.amount }}
                                </h2>
                            </div>
                            <span
                                class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300"
                            >
                                {{ displayLabel(currentInstruction.status) }}
                            </span>
                        </div>

                        <div
                            v-if="currentInstruction.qr_code"
                            class="mt-4 flex flex-col items-center gap-4 rounded-xl border border-sky-200 bg-white p-4 text-center sm:flex-row sm:text-left dark:border-sky-900 dark:bg-slate-950"
                            data-testid="cockpit-funding-qr"
                        >
                            <img
                                :src="currentInstruction.qr_code"
                                :alt="`Exact ${currentInstruction.amount} NetBank QR Ph code`"
                                class="size-40 shrink-0 rounded-lg bg-white object-contain"
                            />
                            <div>
                                <p class="text-sm font-semibold">
                                    Scan to pay exactly
                                    {{ currentInstruction.amount }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Dynamic P2M QR · one Funding Intent · one
                                    destination. Payment does not credit the
                                    Account until NetBank transaction history
                                    confirms settlement.
                                </p>
                            </div>
                        </div>

                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Provider
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{
                                        currentInstruction.institution ??
                                        displayLabel(
                                            currentInstruction.provider,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Expires
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{
                                        displayTime(
                                            currentInstruction.expires_at,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div v-if="currentInstruction.account_name">
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Account name
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{ currentInstruction.account_name }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Funding Intent
                                </dt>
                                <dd class="mt-1 font-mono text-xs">
                                    {{ currentInstruction.reference }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="currentInstruction.funding_address"
                            class="mt-4 rounded-lg border border-sky-200 bg-white p-3 dark:border-sky-900 dark:bg-slate-950"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >
                                Destination account
                            </p>
                            <div
                                class="mt-1 flex flex-wrap items-center justify-between gap-3"
                            >
                                <p
                                    class="font-mono text-sm font-semibold break-all"
                                >
                                    {{ currentInstruction.funding_address }}
                                </p>
                                <CockpitManualCopyButton
                                    :value="currentInstruction.funding_address"
                                    label="Copy account"
                                    helper="Browser-local copy only."
                                />
                            </div>
                        </div>

                        <a
                            v-if="currentInstruction.action_url"
                            :href="currentInstruction.action_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700"
                        >
                            Open provider payment page
                        </a>

                        <p
                            v-if="currentInstruction.simulation_only"
                            class="mt-4 text-xs leading-5 text-emerald-700 dark:text-emerald-300"
                        >
                            Local simulation only. Do not transfer money to this
                            address. No bank or EMI was contacted, and no
                            balance changed.
                        </p>
                        <p
                            v-else
                            class="mt-4 text-xs leading-5 text-slate-600 dark:text-slate-400"
                        >
                            Sensitive settlement access material. Transfer the
                            exact amount before expiry. The Account changes only
                            after independent provider verification.
                        </p>
                    </div>
                    <div
                        v-else
                        class="flex min-h-52 flex-col items-center justify-center text-center"
                    >
                        <p
                            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >
                            Funding instructions will appear here once
                        </p>
                        <p
                            class="mt-1 max-w-md text-xs leading-5 text-slate-500"
                        >
                            Create an intent to receive the exact bank
                            destination or provider payment link. Refreshing
                            later will show the sanitized activity record, not
                            the sensitive instruction payload.
                        </p>
                    </div>
                    <p
                        v-if="instructionError"
                        class="mt-3 text-xs font-medium text-rose-700 dark:text-rose-300"
                        role="alert"
                    >
                        {{ instructionError }}
                    </p>
                </article>
            </section>

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Funding summary"
            >
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ card.label }}
                    </p>
                    <p
                        :class="[
                            'mt-2 text-2xl font-semibold tracking-tight',
                            card.tone,
                        ]"
                    >
                        {{ card.value }}
                    </p>
                    <p
                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ card.helper }}
                    </p>
                </article>
            </section>

            <section
                class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]"
            >
                <article
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                            >
                                Funding rails
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Available providers
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ availableFundingProviders.length }} ready ·
                            {{ funding_read_model.providers.length }} installed
                        </span>
                    </div>
                    <div
                        v-if="funding_read_model.providers.length"
                        class="grid gap-3 p-4 sm:grid-cols-2"
                    >
                        <div
                            v-for="provider in funding_read_model.providers"
                            :key="provider.code"
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p class="font-semibold">
                                    {{ provider.label }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-1 text-[0.65rem] font-semibold tracking-wide uppercase"
                                    :class="{
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300':
                                            provider.status === 'blocked',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300':
                                            provider.status === 'disabled',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300':
                                            provider.status === 'available',
                                    }"
                                >
                                    {{ provider.status }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{
                                    displayLabel(
                                        provider.destination_mode ?? 'shared',
                                    )
                                }}
                                ·
                                {{
                                    provider.destination_reference ??
                                    'Not configured'
                                }}
                            </p>
                            <p
                                class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    provider.status === 'blocked'
                                        ? 'Dedicated funding is unavailable until authoritative destination verification succeeds.'
                                        : provider.status === 'disabled'
                                          ? 'Adapter installed; Funding Intake remains locked until this provider is explicitly enabled.'
                                          : provider.simulation_only
                                            ? 'Local-only Funding Intent happy path with zero bank or EMI calls.'
                                            : 'Signed intake plus independent authoritative status verification.'
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="p-5 text-sm text-slate-500">
                        No funding provider is enabled for this environment.
                    </p>
                </article>

                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                    >
                        Safeguards
                    </p>
                    <h2 class="mt-1 text-lg font-semibold">
                        Non-negotiable controls
                    </h2>
                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="(safeguard, index) in safeguards"
                            :key="safeguard"
                            class="flex gap-3 text-sm leading-5 text-slate-600 dark:text-slate-300"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold text-white dark:bg-sky-300 dark:text-slate-950"
                            >
                                {{ index + 1 }}
                            </span>
                            <span>{{ safeguard }}</span>
                        </li>
                    </ol>
                </article>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                        >
                            Funding activity
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Recent Funding Intents
                        </h2>
                    </div>
                    <span class="text-xs text-slate-500"
                        >Sanitized operational summary</span
                    >
                </div>
                <div
                    v-if="funding_read_model.intents.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full min-w-[56rem] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-5 py-3 font-semibold">
                                    Reference
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    Provider
                                </th>
                                <th class="px-5 py-3 font-semibold">Amount</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">
                                    Last checked
                                </th>
                                <th class="px-5 py-3 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="intent in funding_read_model.intents"
                                :key="intent.reference"
                            >
                                <td
                                    class="px-5 py-3 font-mono text-xs text-slate-700 dark:text-slate-300"
                                >
                                    {{ intent.reference }}
                                </td>
                                <td class="px-5 py-3 font-medium">
                                    {{ displayLabel(intent.provider) }}
                                </td>
                                <td class="px-5 py-3 font-semibold">
                                    {{ intent.amount }}
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            displayLabel(
                                                intent.verification_status,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 text-slate-500 dark:text-slate-400"
                                >
                                    {{ displayTime(intent.last_checked_at) }}
                                </td>
                                <td class="px-5 py-3">
                                    <div
                                        class="flex min-w-52 flex-wrap items-center gap-2"
                                    >
                                        <button
                                            v-if="
                                                intent.can_reopen_instructions
                                            "
                                            type="button"
                                            class="h-8 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-sky-400 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            :disabled="
                                                activeInstructionRequest !==
                                                null
                                            "
                                            :data-testid="`reopen-funding-instructions-${intent.reference}`"
                                            @click="
                                                reopenFundingInstructions(
                                                    intent.reference,
                                                )
                                            "
                                        >
                                            {{
                                                activeInstructionRequest ===
                                                intent.reference
                                                    ? 'Opening…'
                                                    : 'Reopen QR'
                                            }}
                                        </button>
                                        <button
                                            type="button"
                                            class="h-8 rounded-lg bg-sky-600 px-3 text-xs font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="
                                                !intent.can_check_provider ||
                                                activeVerificationCheck !== null
                                            "
                                            :data-testid="`check-netbank-${intent.reference}`"
                                            @click="
                                                checkNetBank(intent.reference)
                                            "
                                        >
                                            {{
                                                activeVerificationCheck ===
                                                intent.reference
                                                    ? 'Checking…'
                                                    : 'Check NetBank'
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <p
                        class="text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        No Funding Intents yet
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        A request will appear here before any incoming funds can
                        be recognized.
                    </p>
                </div>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-funding-approval-queue"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-indigo-700 uppercase dark:text-indigo-300"
                        >
                            Dual control
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Reconciliation approval queue
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Requests are visible across Treasury operators so a
                            different person can approve.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ funding_read_model.approval_queue.length }} pending
                    </span>
                </div>
                <div
                    v-if="funding_read_model.approval_queue.length"
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <div
                        v-for="approval in funding_read_model.approval_queue"
                        :key="approval.reference"
                        class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">
                                    {{
                                        reconciliationActionLabel(
                                            approval.action,
                                        )
                                    }}
                                </p>
                                <span
                                    class="rounded-full bg-indigo-50 px-2 py-1 text-[0.65rem] font-semibold tracking-wide text-indigo-700 uppercase dark:bg-indigo-950/50 dark:text-indigo-300"
                                >
                                    {{ displayLabel(approval.provider) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Case {{ approval.case_reference }} ·
                                {{ displayLabel(approval.reason) }} ·
                                {{ displayTime(approval.requested_at) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Exact immutable evidence only; amount and
                                evidence inputs are disabled by contract.
                            </p>
                        </div>
                        <button
                            v-if="approval.can_approve"
                            type="button"
                            class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="approvalForm.processing"
                            @click="
                                approveFundingReconciliation(approval.reference)
                            "
                        >
                            {{
                                activeApproval === approval.reference
                                    ? 'Approving…'
                                    : 'Approve and execute'
                            }}
                        </button>
                        <span
                            v-else
                            class="shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 dark:border-slate-700"
                        >
                            Awaiting another operator
                        </span>
                    </div>
                </div>
                <p v-else class="px-5 py-6 text-sm text-slate-500">
                    No reconciliation requests are awaiting approval.
                </p>
                <p
                    v-if="approvalForm.errors.approval"
                    class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-300"
                >
                    {{ approvalForm.errors.approval }}
                </p>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-amber-700 uppercase dark:text-amber-300"
                            >
                                Exception control
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">Suspense</h2>
                        </div>
                        <span class="text-sm font-semibold">{{
                            funding_read_model.suspense_cases.length
                        }}</span>
                    </div>
                    <div
                        v-if="funding_read_model.suspense_cases.length"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="item in funding_read_model.suspense_cases"
                            :key="item.reference"
                            class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/20"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-mono text-xs">
                                    {{ item.reference }}
                                </p>
                                <span
                                    class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                                    >{{ displayLabel(item.reason) }}</span
                                >
                            </div>
                            <p
                                class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                {{
                                    item.pending_approval
                                        ? 'Maker request pending independent approval.'
                                        : 'Awaiting a controlled reconciliation request.'
                                }}
                            </p>
                            <div
                                v-if="item.allowed_actions.length"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <button
                                    v-for="action in item.allowed_actions"
                                    :key="action"
                                    type="button"
                                    class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                    :disabled="reconciliationForm.processing"
                                    @click="
                                        requestReconciliation(
                                            item.reference,
                                            action,
                                        )
                                    "
                                >
                                    {{
                                        activeReconciliationCase ===
                                        item.reference
                                            ? 'Submitting…'
                                            : reconciliationActionLabel(action)
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No open funding exceptions.
                    </p>
                    <p
                        v-if="reconciliationForm.errors.reconciliation"
                        class="mt-3 text-xs text-rose-600 dark:text-rose-300"
                    >
                        {{ reconciliationForm.errors.reconciliation }}
                    </p>
                </article>

                <article
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-rose-700 uppercase dark:text-rose-300"
                            >
                                Issuance protection
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Recovery holds
                            </h2>
                        </div>
                        <span class="text-sm font-semibold">{{
                            funding_read_model.recovery_holds.length
                        }}</span>
                    </div>
                    <div
                        v-if="funding_read_model.recovery_holds.length"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="hold in funding_read_model.recovery_holds"
                            :key="hold.reference"
                            class="rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900 dark:bg-rose-950/20"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-mono text-xs">
                                    {{ hold.reference }}
                                </p>
                                <p
                                    class="font-semibold text-rose-800 dark:text-rose-300"
                                >
                                    {{ hold.outstanding }}
                                </p>
                            </div>
                            <p
                                class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                Future verified funding repays this hold before
                                becoming usable.
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No active funding recovery holds.
                    </p>
                </article>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                        >
                            3neti/wallet grammar
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Treasury Inventory
                        </h2>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >read-only positions</span
                    >
                </div>
                <div
                    v-if="funding_read_model.treasury_positions.length"
                    class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="position in funding_read_model.treasury_positions"
                        :key="`${position.provider}-${position.currency}`"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            {{ displayLabel(position.provider) }}
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{ position.recognized }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ displayLabel(position.status) }} · durable
                            Inventory facts
                        </p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">
                    No Treasury Inventory has been recognized from verified
                    funding yet.
                </p>
            </section>
        </div>
    </CockpitLayout>
</template>
