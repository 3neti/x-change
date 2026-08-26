<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store as createPaymentAttempt } from '@/routes/x-change/pay/attempts';
import { store as checkPaymentAttempt } from '@/routes/x-change/pay/attempts/checks';

defineOptions({ layout: null });

type PaymentAttempt = {
    reference: string;
    status: string;
    provider: string;
    amount_minor: number;
    currency: string;
    expires_at: string | null;
    last_checked_at: string | null;
    can_check: boolean;
    qr_code: {
        mime_type: string | null;
        base64_payload: string | null;
        qr_mode: string | null;
        transaction_type: string | null;
        embedded_amount: boolean;
    } | null;
};

type ReceiptPayment = {
    collection_number: number;
    amount_paid_minor: number;
    provider: string;
    receipt_reference: string;
    completed_at: string | null;
};

type PaymentReceipt = {
    pay_code: string;
    amount_paid_minor: number;
    currency: string;
    completed_at: string | null;
    payments: ReceiptPayment[];
};

type PaymentReadModel = {
    pay_code: string;
    currency: string;
    target_amount_minor: number;
    collected_amount_minor: number;
    amount_due_minor: number;
    is_fully_paid: boolean;
    rider_message: string | null;
    provider: string;
    provider_available: boolean;
    can_create_attempt: boolean;
    attempt: PaymentAttempt | null;
    receipt: PaymentReceipt | null;
};

const props = defineProps<{
    payment: PaymentReadModel;
    notice?: string | null;
}>();

const creating = ref(false);
const checking = ref(false);
const selectedMethod = ref<'qr' | null>(props.payment.attempt ? 'qr' : null);

function money(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);
}

const amountDue = computed(() =>
    money(props.payment.amount_due_minor, props.payment.currency),
);

const attemptAmount = computed(() =>
    money(
        props.payment.attempt?.amount_minor ?? 0,
        props.payment.attempt?.currency ?? props.payment.currency,
    ),
);

const qrSource = computed(() => {
    const qr = props.payment.attempt?.qr_code;

    if (
        qr?.mime_type !== 'image/png' ||
        typeof qr.base64_payload !== 'string' ||
        qr.base64_payload === ''
    ) {
        return null;
    }

    return `data:image/png;base64,${qr.base64_payload}`;
});

const expiresAt = computed(() => {
    if (!props.payment.attempt?.expires_at) {
        return null;
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.payment.attempt.expires_at));
});

function dateTime(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function selectQr(): void {
    if (props.payment.provider_available) {
        selectedMethod.value = 'qr';
    }
}

function startPayment(): void {
    if (
        selectedMethod.value !== 'qr' ||
        !props.payment.can_create_attempt ||
        creating.value
    ) {
        return;
    }

    creating.value = true;
    router.post(
        createPaymentAttempt.url(props.payment.pay_code),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}

function checkPaymentStatus(): void {
    const attempt = props.payment.attempt;

    if (!attempt?.can_check || checking.value) {
        return;
    }

    checking.value = true;
    router.post(
        checkPaymentAttempt.url({
            code: props.payment.pay_code,
            attempt: attempt.reference,
        }),
        {},
        {
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                checking.value = false;
            },
        },
    );
}

function printReceipt(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Pay ${payment.pay_code}`" />

    <main
        class="min-h-svh bg-gradient-to-b from-emerald-950 via-slate-950 to-slate-950 px-4 py-8 text-slate-100 print:bg-white print:px-0 print:py-0 print:text-slate-950 sm:px-6 sm:py-12"
    >
        <div class="mx-auto w-full max-w-lg space-y-4">
            <header class="space-y-2 text-center print:hidden">
                <p
                    class="text-xs font-semibold tracking-[0.24em] text-emerald-300 uppercase"
                >
                    Secure Pay Code payment
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Pay {{ payment.pay_code }}
                </h1>
                <p class="text-sm text-slate-400">
                    Review the purpose and invoice before choosing how to pay.
                </p>
            </header>

            <div
                v-if="notice"
                class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-center text-sm text-emerald-100 print:hidden"
                role="status"
            >
                {{ notice }}
            </div>

            <section
                data-testid="payer-xray-step"
                class="rounded-3xl border border-white/10 bg-white/[0.06] px-5 py-5 shadow-xl shadow-black/20 backdrop-blur print:hidden sm:px-6"
            >
                <p
                    class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                >
                    1 · X-Ray
                </p>
                <h2 class="mt-2 text-lg font-semibold">
                    What this payment is for
                </h2>
                <p
                    v-if="payment.rider_message"
                    class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-200"
                >
                    {{ payment.rider_message }}
                </p>
                <p v-else class="mt-3 text-sm leading-6 text-slate-400">
                    The issuer did not include a payment message.
                </p>
            </section>

            <section
                data-testid="payer-invoice-step"
                class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.06] shadow-2xl shadow-black/30 backdrop-blur print:hidden"
            >
                <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        2 · Invoice
                    </p>
                    <h2 class="mt-2 text-lg font-semibold">
                        Pay Code {{ payment.pay_code }}
                    </h2>
                </div>

                <dl class="space-y-3 px-5 py-5 text-sm sm:px-6">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Target amount</dt>
                        <dd class="font-medium tabular-nums">
                            {{
                                money(
                                    payment.target_amount_minor,
                                    payment.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Already collected</dt>
                        <dd class="font-medium tabular-nums">
                            {{
                                money(
                                    payment.collected_amount_minor,
                                    payment.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <div
                        class="flex items-end justify-between gap-4 border-t border-white/10 pt-4"
                    >
                        <dt class="font-medium">Amount due</dt>
                        <dd
                            class="text-2xl font-semibold tabular-nums text-emerald-300"
                        >
                            {{ amountDue }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="!payment.is_fully_paid"
                    data-testid="payer-funding-methods-step"
                    class="border-t border-white/10 px-5 py-5 sm:px-6"
                >
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase"
                    >
                        3 · Funding method
                    </p>
                    <h2 class="mt-2 text-lg font-semibold">
                        Choose how to pay
                    </h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <button
                            type="button"
                            data-testid="payer-method-qr"
                            class="min-h-24 rounded-2xl border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                selectedMethod === 'qr'
                                    ? 'border-emerald-300 bg-emerald-300/15'
                                    : 'border-white/10 bg-black/20 hover:border-emerald-300/50'
                            "
                            :disabled="!payment.provider_available"
                            :aria-pressed="selectedMethod === 'qr'"
                            @click="selectQr"
                        >
                            <span class="block font-semibold">QR Code</span>
                            <span class="mt-1 block text-xs text-slate-400">
                                {{
                                    payment.provider_available
                                        ? 'QR Ph'
                                        : 'Unavailable'
                                }}
                            </span>
                        </button>
                        <button
                            type="button"
                            data-testid="payer-method-bank"
                            class="min-h-24 cursor-not-allowed rounded-2xl border border-white/10 bg-black/20 p-4 text-left opacity-55"
                            disabled
                        >
                            <span class="block font-semibold"
                                >Bank Transfer</span
                            >
                            <span class="mt-1 block text-xs text-slate-400"
                                >Not yet available</span
                            >
                        </button>
                        <button
                            type="button"
                            data-testid="payer-method-pay-code"
                            class="min-h-24 cursor-not-allowed rounded-2xl border border-white/10 bg-black/20 p-4 text-left opacity-55"
                            disabled
                        >
                            <span class="block font-semibold">Pay Code</span>
                            <span class="mt-1 block text-xs text-slate-400"
                                >Not yet available</span
                            >
                        </button>
                    </div>
                </div>

                <div
                    v-if="payment.attempt && !payment.is_fully_paid"
                    class="space-y-5 px-5 py-5 sm:px-6 sm:py-6"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <p class="text-xs text-slate-400">
                                One-time payment
                            </p>
                            <p class="font-semibold tabular-nums">
                                {{ attemptAmount }}
                            </p>
                        </div>
                        <span
                            class="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1 text-xs font-medium text-amber-200"
                        >
                            {{ payment.attempt.status.replaceAll('_', ' ') }}
                        </span>
                    </div>

                    <div
                        v-if="qrSource"
                        class="mx-auto w-fit rounded-3xl bg-white p-4 shadow-xl shadow-black/25"
                    >
                        <img
                            :src="qrSource"
                            :alt="`QR Ph code for ${attemptAmount}`"
                            class="size-64 max-w-full"
                        />
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-amber-300/20 bg-amber-300/10 px-4 py-5 text-center text-sm text-amber-100"
                    >
                        The provider did not return a usable QR image.
                    </div>

                    <div
                        class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm"
                    >
                        <p class="font-medium">Scan with any QR Ph app</p>
                        <p class="mt-1 text-slate-400">
                            Pay exactly {{ attemptAmount }}. The QR is bound to
                            this Pay Code and cannot fund your x-change Account.
                        </p>
                        <p v-if="expiresAt" class="mt-2 text-xs text-slate-500">
                            Expires {{ expiresAt }}
                        </p>
                    </div>

                    <button
                        type="button"
                        data-testid="payer-check-status"
                        class="min-h-12 w-full rounded-2xl border border-emerald-300/30 bg-emerald-300/10 px-5 py-3 text-sm font-semibold text-emerald-200 transition hover:bg-emerald-300/20 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!payment.attempt.can_check || checking"
                        @click="checkPaymentStatus"
                    >
                        {{
                            checking
                                ? 'Checking payment status…'
                                : 'Check payment status'
                        }}
                    </button>
                </div>

                <div
                    v-else-if="!payment.is_fully_paid"
                    class="space-y-4 px-5 py-6 sm:px-6"
                >
                    <div
                        v-if="!payment.provider_available"
                        class="rounded-2xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100"
                    >
                        QR Code payment is not available in this environment.
                    </div>

                    <button
                        v-if="selectedMethod === 'qr'"
                        type="button"
                        data-testid="payer-create-qr"
                        class="min-h-12 w-full rounded-2xl bg-emerald-400 px-5 py-3 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!payment.can_create_attempt || creating"
                        @click="startPayment"
                    >
                        {{
                            creating
                                ? 'Preparing secure QR…'
                                : 'Create exact QR Ph payment'
                        }}
                    </button>
                    <p class="text-center text-xs text-slate-500">
                        Creating instructions does not mark this Pay Code paid.
                        Authoritative provider history must confirm settlement.
                    </p>
                </div>
            </section>

            <section
                v-if="payment.is_fully_paid"
                data-testid="payer-receipt"
                class="rounded-3xl border border-emerald-300/20 bg-white/[0.07] px-5 py-6 shadow-2xl shadow-black/30 print:rounded-none print:border-0 print:bg-white print:px-8 print:py-8 print:shadow-none sm:px-6"
            >
                <p
                    class="text-xs font-semibold tracking-[0.18em] text-emerald-300 uppercase print:text-slate-600"
                >
                    4 · Payment confirmation
                </p>
                <h2
                    class="mt-2 text-2xl font-semibold text-emerald-300 print:text-slate-950"
                >
                    Payment complete
                </h2>

                <template v-if="payment.receipt">
                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400 print:text-slate-600">
                                Pay Code
                            </dt>
                            <dd class="font-semibold">
                                {{ payment.receipt.pay_code }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400 print:text-slate-600">
                                Amount paid
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    money(
                                        payment.receipt.amount_paid_minor,
                                        payment.receipt.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400 print:text-slate-600">
                                Currency
                            </dt>
                            <dd>{{ payment.receipt.currency }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400 print:text-slate-600">
                                Completed
                            </dt>
                            <dd class="text-right">
                                {{ dateTime(payment.receipt.completed_at) }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="mt-6 space-y-3 border-t border-white/10 pt-5 print:border-slate-200"
                    >
                        <article
                            v-for="receiptPayment in payment.receipt.payments"
                            :key="receiptPayment.receipt_reference"
                            class="rounded-2xl border border-white/10 bg-black/15 px-4 py-3 text-sm print:border-slate-200 print:bg-white"
                        >
                            <div class="flex justify-between gap-4">
                                <span class="font-medium">
                                    {{ receiptPayment.provider }}
                                </span>
                                <span class="tabular-nums">
                                    {{
                                        money(
                                            receiptPayment.amount_paid_minor,
                                            payment.receipt.currency,
                                        )
                                    }}
                                </span>
                            </div>
                            <p
                                class="mt-2 break-all text-xs text-slate-400 print:text-slate-600"
                            >
                                Receipt reference
                                {{ receiptPayment.receipt_reference }}
                            </p>
                            <p
                                class="mt-1 text-xs text-slate-500 print:text-slate-600"
                            >
                                {{ dateTime(receiptPayment.completed_at) }}
                            </p>
                        </article>
                    </div>

                    <button
                        type="button"
                        data-testid="payer-print-receipt"
                        class="mt-6 min-h-12 w-full rounded-2xl bg-emerald-400 px-5 py-3 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-300 print:hidden"
                        @click="printReceipt"
                    >
                        Print / Save as PDF
                    </button>
                </template>
                <p
                    v-else
                    class="mt-4 text-sm text-slate-400 print:text-slate-600"
                >
                    The payment is complete. Receipt evidence is being
                    reconciled.
                </p>
            </section>

            <p
                class="px-4 text-center text-xs leading-5 text-slate-500 print:hidden"
            >
                Payment attempts are session-bound and expire. Payer mobile
                numbers are evidence only and never select the receiving
                Account.
            </p>
        </div>
    </main>
</template>
