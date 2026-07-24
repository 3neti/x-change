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

type PaymentReadModel = {
    pay_code: string;
    currency: string;
    target_amount_minor: number;
    collected_amount_minor: number;
    amount_due_minor: number;
    is_fully_paid: boolean;
    provider: string;
    provider_available: boolean;
    can_create_attempt: boolean;
    attempt: PaymentAttempt | null;
};

const props = defineProps<{
    payment: PaymentReadModel;
    notice?: string | null;
}>();

const creating = ref(false);
const checking = ref(false);

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

function startPayment(): void {
    if (!props.payment.can_create_attempt || creating.value) {
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

function checkNetBank(): void {
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
</script>

<template>
    <Head :title="`Pay ${payment.pay_code}`" />

    <main
        class="min-h-svh bg-gradient-to-b from-emerald-950 via-slate-950 to-slate-950 px-4 py-8 text-slate-100 sm:px-6 sm:py-12"
    >
        <div class="mx-auto w-full max-w-lg space-y-4">
            <header class="space-y-2 text-center">
                <p
                    class="text-xs font-semibold tracking-[0.24em] text-emerald-300 uppercase"
                >
                    Secure Pay Code payment
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Pay {{ payment.pay_code }}
                </h1>
                <p class="text-sm text-slate-400">
                    Exact QR Ph payment. This does not top up an Account.
                </p>
            </header>

            <div
                v-if="notice"
                class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-center text-sm text-emerald-100"
                role="status"
            >
                {{ notice }}
            </div>

            <section
                class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.06] shadow-2xl shadow-black/30 backdrop-blur"
            >
                <div class="border-b border-white/10 px-5 py-4 sm:px-6">
                    <p class="text-xs text-slate-400">Amount due</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ amountDue }}
                    </p>
                </div>

                <div
                    v-if="payment.is_fully_paid"
                    class="space-y-2 px-5 py-8 text-center sm:px-6"
                >
                    <p class="text-lg font-semibold text-emerald-300">
                        Payment complete
                    </p>
                    <p class="text-sm text-slate-400">
                        This Pay Code has received its required amount.
                    </p>
                </div>

                <div
                    v-else-if="payment.attempt"
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
                        class="min-h-12 w-full rounded-2xl border border-emerald-300/30 bg-emerald-300/10 px-5 py-3 text-sm font-semibold text-emerald-200 transition hover:bg-emerald-300/20 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!payment.attempt.can_check || checking"
                        @click="checkNetBank"
                    >
                        {{ checking ? 'Checking NetBank…' : 'Check NetBank' }}
                    </button>
                </div>

                <div v-else class="space-y-4 px-5 py-6 sm:px-6">
                    <div
                        v-if="!payment.provider_available"
                        class="rounded-2xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-sm text-amber-100"
                    >
                        NetBank payment is not available in this environment.
                    </div>

                    <button
                        type="button"
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
                        NetBank history must confirm settlement.
                    </p>
                </div>
            </section>

            <p class="px-4 text-center text-xs leading-5 text-slate-500">
                Payment attempts are session-bound and expire. Payer mobile
                numbers are evidence only and never select the receiving
                Account.
            </p>
        </div>
    </main>
</template>
