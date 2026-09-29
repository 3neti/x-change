<script setup lang="ts">
import { Check, Circle, LoaderCircle, ShieldCheck, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type {
    CockpitOnDemandIssuanceFundingProjection,
    CockpitPrimaryFundingWorkspaceMode,
} from '../types';
import CockpitFundingMethodSelector from './CockpitFundingMethodSelector.vue';

const props = defineProps<{
    open: boolean;
    projection: CockpitOnDemandIssuanceFundingProjection | null;
}>();

const emit = defineEmits<{
    issued: [projection: CockpitOnDemandIssuanceFundingProjection];
    cancelled: [];
    closed: [];
}>();

const current = ref<CockpitOnDemandIssuanceFundingProjection | null>(props.projection);
const selectedMode = ref<CockpitPrimaryFundingWorkspaceMode>('bank_transfer');
const checking = ref(false);
const cancelling = ref(false);
const error = ref<string | null>(null);
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let pollAttempt = 0;
let emittedIssuedReference: string | null = null;
const pollDelays = [2000, 3000, 5000, 8000, 13000];

const terminal = computed(() =>
    ['issued', 'cancelled', 'expired'].includes(current.value?.status ?? ''),
);
const needsAttention = computed(() => current.value?.status === 'issuance_attention');
const amount = computed(() => {
    const order = current.value?.order;

    if (order === undefined) {
        return '';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: order.currency,
    }).format(order.expected_payment_minor / 100);
});
const instructions = computed<Record<string, unknown>>(
    () => current.value?.funding_selector.bank_transfer.instructions ?? {},
);
const fixedQrPh = computed(() => current.value?.funding_selector.qr_ph ?? null);
const canCloseForNow = computed(() =>
    ['payer_acknowledged', 'verifying', 'funded', 'issuing'].includes(
        current.value?.status ?? '',
    ),
);

watch(
    () => props.projection,
    (projection) => {
        current.value = projection;
        pollAttempt = 0;
        selectedMode.value = projection?.funding_selector.default_mode ?? 'bank_transfer';
        schedulePoll();
    },
    { immediate: true },
);

watch(
    () => props.open,
    () => schedulePoll(),
);

onBeforeUnmount(clearPoll);

function csrfHeader(): Record<string, string> {
    const token = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

function clearPoll(): void {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function schedulePoll(): void {
    clearPoll();

    if (!props.open || current.value === null || terminal.value) {
        return;
    }

    const delay = pollDelays[Math.min(pollAttempt, pollDelays.length - 1)];
    pollTimer = setTimeout(() => void refresh(), delay);
}

async function refresh(): Promise<void> {
    if (current.value === null || checking.value) {
        schedulePoll();

        return;
    }

    try {
        const response = await fetch(current.value.actions.show, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok) {
            const next = (await response.json()) as CockpitOnDemandIssuanceFundingProjection;
            const changed = next.status !== current.value.status;
            current.value = next;
            pollAttempt = changed ? 0 : pollAttempt + 1;
            error.value = null;

            if (
                current.value.status === 'issued' &&
                emittedIssuedReference !== current.value.order.reference
            ) {
                emittedIssuedReference = current.value.order.reference;
                emit('issued', current.value);
            }
        } else {
            pollAttempt += 1;
            error.value = 'Verification temporarily unavailable. You do not need to pay again.';
        }
    } catch {
        pollAttempt += 1;
        error.value = 'Verification temporarily unavailable. You do not need to pay again.';
    } finally {
        schedulePoll();
    }
}

function closeForNow(): void {
    clearPoll();
    emit('closed');
}

async function acknowledge(): Promise<void> {
    if (current.value === null || checking.value) {
        return;
    }

    checking.value = true;
    error.value = null;

    try {
        const response = await fetch(current.value.actions.acknowledge, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = (await response.json()) as CockpitOnDemandIssuanceFundingProjection & { message?: string };

        if (!response.ok) {
            throw new Error(body.message ?? 'The payment check could not be started.');
        }

        current.value = body;
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'The payment check failed.';
    } finally {
        checking.value = false;
        schedulePoll();
    }
}

async function cancel(): Promise<void> {
    if (current.value === null || !current.value.order.can_cancel || cancelling.value) {
        return;
    }

    cancelling.value = true;
    error.value = null;

    try {
        const response = await fetch(current.value.actions.cancel, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = (await response.json()) as CockpitOnDemandIssuanceFundingProjection & { message?: string };

        if (!response.ok) {
            throw new Error(body.message ?? 'The funding order could not be cancelled.');
        }

        current.value = body;
        emit('cancelled');
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Cancellation failed.';
    } finally {
        cancelling.value = false;
    }
}

function text(value: unknown): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open && current"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="on-demand-funding-title"
            data-testid="on-demand-issuance-funding-dialog"
            @click.self.stop
            @keydown.esc.prevent.stop
        >
            <section
                class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-5 shadow-2xl dark:bg-slate-950 sm:p-7"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.18em] text-emerald-600 uppercase">
                            On-demand issuance funding
                        </p>
                        <h2 id="on-demand-funding-title" class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">
                            {{ needsAttention ? 'Funding needs attention' : `Pay ${amount} to issue this Pay Code` }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ needsAttention
                                ? 'No payment should be made from these instructions. Cancel safely and prepare a fresh order.'
                                : 'The instruction is frozen. This window stays open until payment is verified, the Pay Code is issued, or you cancel safely.' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-8 shrink-0 text-emerald-600" aria-hidden="true" />
                        <button
                            v-if="canCloseForNow"
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-900"
                            aria-label="Close for now"
                            @click="closeForNow"
                        >
                            <X class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </header>

                <ol class="mt-5 grid grid-cols-5 gap-1" aria-label="Funding progress">
                    <li
                        v-for="step in current.lifecycle.steps"
                        :key="step.key"
                        class="min-w-0 text-center"
                        :aria-current="step.state === 'current' ? 'step' : undefined"
                    >
                        <div class="flex items-center gap-1">
                            <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
                            <Check v-if="step.state === 'complete'" class="size-4 text-emerald-600" aria-hidden="true" />
                            <LoaderCircle v-else-if="step.state === 'current'" class="size-4 animate-spin text-emerald-600" aria-hidden="true" />
                            <Circle v-else class="size-3 text-slate-300 dark:text-slate-700" aria-hidden="true" />
                            <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" />
                        </div>
                        <span class="mt-1 block truncate text-[0.62rem] font-semibold text-slate-500">{{ step.label }}</span>
                    </li>
                </ol>

                <div class="mt-4 grid gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Order reference</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ current.order.reference }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500">Expires</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ current.order.expires_at ? new Date(current.order.expires_at).toLocaleString() : 'No expiry' }}</span>
                    </div>
                    <p class="border-t border-slate-200 pt-2 text-slate-600 dark:border-slate-800 dark:text-slate-300" role="status">
                        {{ current.lifecycle.message }}
                    </p>
                </div>

                <CockpitFundingMethodSelector
                    v-if="!needsAttention"
                    v-model="selectedMode"
                    :selector="current.funding_selector"
                />

                <div
                    v-if="needsAttention"
                    class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    role="status"
                >
                    Funding instructions could not be prepared safely. No Pay Code was issued and payment has not been accepted for this order.
                </div>

                <div
                    v-else-if="selectedMode === 'bank_transfer'"
                    class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="on-demand-bank-transfer-instructions"
                >
                    <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Exact transfer amount</p>
                    <p class="mt-1 text-3xl font-black text-slate-950 dark:text-white">{{ amount }}</p>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div v-if="text(instructions.institution)" class="flex justify-between gap-4">
                            <dt class="text-slate-500">Bank</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ text(instructions.institution) }}</dd>
                        </div>
                        <div v-if="text(instructions.account_name)" class="flex justify-between gap-4">
                            <dt class="text-slate-500">Account name</dt>
                            <dd class="text-right font-semibold text-slate-900 dark:text-white">{{ text(instructions.account_name) }}</dd>
                        </div>
                        <div v-if="text(instructions.funding_address)" class="flex justify-between gap-4">
                            <dt class="text-slate-500">Account number</dt>
                            <dd class="font-mono font-semibold text-slate-900 dark:text-white">{{ text(instructions.funding_address) }}</dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-else-if="selectedMode === 'self_top_up' && fixedQrPh?.fixed_amount && fixedQrPh.image"
                    class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-800 dark:bg-slate-900"
                    data-testid="on-demand-fixed-qr-ph"
                >
                    <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Exact QR Ph amount</p>
                    <p class="mt-1 text-3xl font-black text-slate-950 dark:text-white">{{ amount }}</p>
                    <img
                        :src="fixedQrPh.image"
                        alt="Order-specific fixed-amount QR Ph"
                        class="mx-auto mt-4 aspect-square w-full max-w-72 rounded-2xl bg-white p-3 shadow-sm"
                    />
                    <p class="mx-auto mt-4 max-w-sm text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {{ fixedQrPh.notice }}
                    </p>
                </div>

                <div v-else class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    {{ current.funding_selector.methods.find((method) => method.workspace_mode === selectedMode)?.unavailable_reason }}
                </div>

                <p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700" role="alert">
                    {{ error }}
                </p>

                <footer v-if="current.status !== 'issued'" class="mt-6 grid gap-3 sm:grid-cols-[1fr_auto]">
                    <button
                        v-if="!needsAttention"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="checking"
                        data-testid="on-demand-payment-check"
                        @click="acknowledge"
                    >
                        <LoaderCircle v-if="checking" class="size-4 animate-spin" aria-hidden="true" />
                        {{ current.status === 'awaiting_payment'
                            ? (selectedMode === 'self_top_up'
                                ? 'I’ve paid by QR Ph — Check payment'
                                : 'I’ve made the transfer — Check payment')
                            : 'Check again' }}
                    </button>
                    <button
                        v-if="canCloseForNow"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 px-4 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200"
                        @click="closeForNow"
                    >
                        Close for now
                    </button>
                    <button
                        v-if="current.order.can_cancel"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
                        :disabled="cancelling"
                        @click="cancel"
                    >
                        <X class="size-4" aria-hidden="true" />
                        Cancel safely
                    </button>
                </footer>
            </section>
        </div>
    </Teleport>
</template>
