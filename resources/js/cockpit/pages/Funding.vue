<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { approve as approveReconciliation } from '@/routes/x-change/cockpit/funding/reconciliations';
import { store as storeFundingIntent } from '@/routes/x-change/cockpit/funding/intents';
import { store as storeReconciliationRequest } from '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests';
import { computed, ref } from 'vue';
import CockpitManualCopyButton from '../components/CockpitManualCopyButton.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitFundingPageProps } from '../types';

const props = defineProps<CockpitFundingPageProps>();
const amount = ref('');
const amountError = ref<string | null>(null);
const activeReconciliationCase = ref<string | null>(null);
const activeApproval = ref<string | null>(null);
const form = useForm({
    provider: props.funding_read_model.providers[0]?.code ?? '',
    amount_minor: 0,
    currency: 'PHP',
    idempotency_key: newIdempotencyKey(),
});
const reconciliationForm = useForm({
    action: '',
});
const approvalForm = useForm({});
const clientAmountError = computed(() => {
    if (amount.value === '' || amountToMinor(amount.value) !== null) {
        return null;
    }

    return 'Enter an amount greater than zero with no more than two decimal places.';
});

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
        helper: 'Reversed funding still held against future usable balance.',
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const safeguards = [
    'A Funding Intent must exist before money can be recognized.',
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

function reconciliationActionLabel(action: string): string {
    return (
        {
            retry_verification: 'Request verification retry',
            match_verified_observation: 'Request exact evidence match',
            compensate_verified_posting: 'Request verified posting',
        }[action] ?? displayLabel(action)
    );
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
                        This creates exact provider instructions. It does not
                        change Internal Balance or Usable Balance.
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
                                :disabled="
                                    form.processing ||
                                    funding_read_model.providers.length === 0
                                "
                                data-testid="cockpit-funding-provider"
                            >
                                <option
                                    v-for="provider in funding_read_model.providers"
                                    :key="provider.code"
                                    :value="provider.code"
                                >
                                    {{ provider.label }}
                                </option>
                            </select>
                            <span
                                v-if="form.errors.provider"
                                class="mt-1 block text-xs text-rose-600"
                                >{{ form.errors.provider }}</span
                            >
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
                                funding_read_model.providers.length === 0
                            "
                            data-testid="cockpit-funding-submit"
                        >
                            {{
                                form.processing
                                    ? 'Creating instructions…'
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
                        funding_instruction
                            ? 'border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/20'
                            : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'
                    "
                    data-testid="cockpit-funding-instruction"
                >
                    <div v-if="funding_instruction">
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
                                    {{ funding_instruction.amount }}
                                </h2>
                            </div>
                            <span
                                class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300"
                            >
                                {{ displayLabel(funding_instruction.status) }}
                            </span>
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
                                        funding_instruction.institution ??
                                        displayLabel(
                                            funding_instruction.provider,
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
                                            funding_instruction.expires_at,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div v-if="funding_instruction.account_name">
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Account name
                                </dt>
                                <dd class="mt-1 text-sm font-medium">
                                    {{ funding_instruction.account_name }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                                >
                                    Funding Intent
                                </dt>
                                <dd class="mt-1 font-mono text-xs">
                                    {{ funding_instruction.reference }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="funding_instruction.funding_address"
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
                                    {{ funding_instruction.funding_address }}
                                </p>
                                <CockpitManualCopyButton
                                    :value="funding_instruction.funding_address"
                                    label="Copy account"
                                    helper="Browser-local copy only."
                                />
                            </div>
                        </div>

                        <a
                            v-if="funding_instruction.action_url"
                            :href="funding_instruction.action_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700"
                        >
                            Open provider payment page
                        </a>

                        <p
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
                            {{ funding_read_model.providers.length }} connected
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
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-[0.65rem] font-semibold tracking-wide text-emerald-700 uppercase dark:bg-emerald-950/60 dark:text-emerald-300"
                                >
                                    {{ provider.status }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                Signed intake plus independent authoritative
                                status verification.
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
                    <table class="w-full min-w-[44rem] text-left text-sm">
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
                                <th class="px-5 py-3 font-semibold">Created</th>
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
                                        {{ displayLabel(intent.status) }}
                                    </span>
                                </td>
                                <td
                                    class="px-5 py-3 text-slate-500 dark:text-slate-400"
                                >
                                    {{ displayTime(intent.created_at) }}
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
