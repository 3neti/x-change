<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CreditCard,
    Loader2,
    Printer,
    ReceiptText,
    RefreshCw,
    ScanLine,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
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
    poll_interval_ms: number;
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
const shouldPoll = computed(
    () =>
        props.payment.attempt !== null &&
        props.payment.attempt.can_check === true &&
        !props.payment.is_fully_paid,
);
const { start: startPaymentPoll, stop: stopPaymentPoll } = usePoll(
    Math.max(1000, props.payment.poll_interval_ms ?? 5000),
    { only: ['payment', 'notice'] },
    { autoStart: shouldPoll.value, mode: 'rest' },
);

watch(shouldPoll, (poll) => {
    if (poll) {
        startPaymentPoll();

        return;
    }

    stopPaymentPoll();
});

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
    <ClaimStepShell
        :tone="payment.is_fully_paid ? 'success' : 'neutral'"
        width="lg"
        :centered="false"
    >
        <div class="space-y-5">
            <header class="space-y-2 text-center print:hidden">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-primary uppercase"
                >
                    Secure Pay Code payment
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Pay {{ payment.pay_code }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Review the purpose and invoice before choosing how to pay.
                </p>
            </header>

            <div
                v-if="notice"
                class="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-center text-sm text-foreground print:hidden"
                role="status"
            >
                {{ notice }}
            </div>

            <Card
                data-testid="payer-xray-step"
                class="border-primary/10 shadow-none print:hidden"
            >
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <ReceiptText class="h-8 w-8 text-primary" />
                        <div>
                            <CardDescription>1 · X-Ray</CardDescription
                            ><CardTitle>What this payment is for</CardTitle>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="payment.rider_message"
                        class="whitespace-pre-line text-sm leading-6 text-foreground"
                    >
                        {{ payment.rider_message }}
                    </p>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        The issuer did not include a payment message.
                    </p>
                </CardContent>
            </Card>

            <Card
                data-testid="payer-invoice-step"
                class="border-primary/10 shadow-none print:hidden"
            >
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <CreditCard class="h-8 w-8 text-primary" />
                        <div>
                            <CardDescription>2 · Invoice</CardDescription
                            ><CardTitle
                                >Pay Code {{ payment.pay_code }}</CardTitle
                            >
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="rounded-lg bg-muted p-4 text-center">
                        <p class="text-sm text-muted-foreground">Amount due</p>
                        <p
                            class="mt-1 text-3xl font-bold tracking-tight text-foreground tabular-nums"
                        >
                            {{ amountDue }}
                        </p>
                        <Badge variant="outline" class="mt-2">{{
                            payment.pay_code
                        }}</Badge>
                    </div>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-muted-foreground">Target amount</dt>
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
                            <dt class="text-muted-foreground">
                                Already collected
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{
                                    money(
                                        payment.collected_amount_minor,
                                        payment.currency,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card
                v-if="!payment.is_fully_paid"
                data-testid="payer-funding-methods-step"
                class="border-primary/10 shadow-none print:hidden"
            >
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <ScanLine class="h-8 w-8 text-primary" />
                        <div>
                            <CardDescription>3 · Funding method</CardDescription
                            ><CardTitle>Choose how to pay</CardTitle>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <Button
                            type="button"
                            data-testid="payer-method-qr"
                            variant="outline"
                            class="h-auto min-h-24 justify-start whitespace-normal p-4 text-left"
                            :class="
                                selectedMethod === 'qr'
                                    ? 'border-primary bg-primary/5'
                                    : ''
                            "
                            :disabled="!payment.provider_available"
                            :aria-pressed="selectedMethod === 'qr'"
                            @click="selectQr"
                        >
                            <span
                                ><span class="block font-semibold">QR Code</span
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{
                                        payment.provider_available
                                            ? 'QR Ph'
                                            : 'Unavailable'
                                    }}</span
                                ></span
                            >
                        </Button>
                        <Button
                            type="button"
                            data-testid="payer-method-bank"
                            variant="outline"
                            class="h-auto min-h-24 cursor-not-allowed justify-start whitespace-normal p-4 text-left"
                            disabled
                        >
                            <span
                                ><span class="block font-semibold"
                                    >Bank Transfer</span
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >Not yet available</span
                                ></span
                            >
                        </Button>
                        <Button
                            type="button"
                            data-testid="payer-method-pay-code"
                            variant="outline"
                            class="h-auto min-h-24 cursor-not-allowed justify-start whitespace-normal p-4 text-left"
                            disabled
                        >
                            <span
                                ><span class="block font-semibold"
                                    >Pay Code</span
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >Not yet available</span
                                ></span
                            >
                        </Button>
                    </div>

                    <template v-if="payment.attempt">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    One-time payment
                                </p>
                                <p class="font-semibold tabular-nums">
                                    {{ attemptAmount }}
                                </p>
                            </div>
                            <Badge variant="secondary">{{
                                payment.attempt.status.replaceAll('_', ' ')
                            }}</Badge>
                        </div>
                        <div
                            v-if="qrSource"
                            class="mx-auto w-fit rounded-xl border border-border bg-white p-4 shadow-sm"
                        >
                            <img
                                :src="qrSource"
                                :alt="`QR Ph code for ${attemptAmount}`"
                                class="size-64 max-w-full"
                            />
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-amber-500/25 bg-amber-500/10 px-4 py-5 text-center text-sm text-foreground"
                        >
                            The provider did not return a usable QR image.
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted p-4"
                        >
                            <p class="text-sm font-medium">
                                Scan with any QR Ph app
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Pay exactly {{ attemptAmount }}. The QR is bound
                                to this Pay Code and cannot fund your x-change
                                Account.
                            </p>
                            <p
                                v-if="expiresAt"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Expires {{ expiresAt }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            data-testid="payer-check-status"
                            variant="outline"
                            class="w-full"
                            size="lg"
                            :disabled="!payment.attempt.can_check || checking"
                            @click="checkPaymentStatus"
                        >
                            <Loader2
                                v-if="checking"
                                class="mr-2 h-4 w-4 animate-spin"
                            /><RefreshCw v-else class="mr-2 h-4 w-4" />{{
                                checking
                                    ? 'Checking payment status…'
                                    : 'Check payment status'
                            }}
                        </Button>
                    </template>

                    <template v-else>
                        <div
                            v-if="!payment.provider_available"
                            class="rounded-lg border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
                        >
                            QR Code payment is not available in this
                            environment.
                        </div>
                        <Button
                            v-if="selectedMethod === 'qr'"
                            type="button"
                            data-testid="payer-create-qr"
                            class="w-full"
                            size="lg"
                            :disabled="!payment.can_create_attempt || creating"
                            @click="startPayment"
                        >
                            <Loader2
                                v-if="creating"
                                class="mr-2 h-4 w-4 animate-spin"
                            />{{
                                creating
                                    ? 'Preparing secure QR…'
                                    : 'Create exact QR Ph payment'
                            }}
                        </Button>
                        <p class="text-center text-xs text-muted-foreground">
                            Creating instructions does not mark this Pay Code
                            paid. Authoritative provider history must confirm
                            settlement.
                        </p>
                    </template>
                </CardContent>
            </Card>

            <Card
                v-if="payment.is_fully_paid"
                data-testid="payer-receipt"
                class="border-primary/20 shadow-none print:border-0 print:shadow-none"
            >
                <CardHeader class="text-center">
                    <CheckCircle2
                        class="mx-auto h-14 w-14 text-primary"
                        aria-hidden="true"
                    />
                    <CardTitle class="mt-3 text-2xl"
                        >Payment received</CardTitle
                    >
                    <CardDescription
                        >This Pay Code has been paid in full.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <template v-if="payment.receipt">
                        <div class="rounded-lg bg-muted p-5 text-center">
                            <p class="text-sm text-muted-foreground">
                                Amount paid
                            </p>
                            <p
                                class="mt-1 text-3xl font-bold tracking-tight text-foreground tabular-nums"
                            >
                                {{
                                    money(
                                        payment.receipt.amount_paid_minor,
                                        payment.receipt.currency,
                                    )
                                }}
                            </p>
                            <Badge variant="outline" class="mt-2">{{
                                payment.receipt.pay_code
                            }}</Badge>
                        </div>
                        <p
                            v-if="payment.rider_message"
                            class="mt-5 whitespace-pre-line rounded-lg border border-primary/10 bg-primary/5 p-4 text-center text-sm leading-6 text-foreground"
                        >
                            {{ payment.rider_message }}
                        </p>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Currency</dt>
                                <dd>{{ payment.receipt.currency }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Completed</dt>
                                <dd class="text-right">
                                    {{ dateTime(payment.receipt.completed_at) }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-5 space-y-3 border-t border-border pt-5">
                            <article
                                v-for="receiptPayment in payment.receipt
                                    .payments"
                                :key="receiptPayment.receipt_reference"
                                class="rounded-lg border border-border bg-muted/50 p-4 text-sm"
                            >
                                <div class="flex justify-between gap-4">
                                    <span class="font-medium">{{
                                        receiptPayment.provider
                                    }}</span
                                    ><span class="tabular-nums">{{
                                        money(
                                            receiptPayment.amount_paid_minor,
                                            payment.receipt.currency,
                                        )
                                    }}</span>
                                </div>
                                <p
                                    class="mt-2 break-all text-xs text-muted-foreground"
                                >
                                    Receipt reference
                                    {{ receiptPayment.receipt_reference }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ dateTime(receiptPayment.completed_at) }}
                                </p>
                            </article>
                        </div>
                    </template>
                    <p v-else class="text-center text-sm text-muted-foreground">
                        The payment is complete. Receipt evidence is being
                        reconciled.
                    </p>
                </CardContent>
                <CardFooter v-if="payment.receipt" class="print:hidden">
                    <Button
                        type="button"
                        data-testid="payer-print-receipt"
                        class="w-full"
                        size="lg"
                        @click="printReceipt"
                        ><Printer class="mr-2 h-4 w-4" />Print / Save as
                        PDF</Button
                    >
                </CardFooter>
            </Card>

            <p
                class="px-4 text-center text-xs leading-5 text-muted-foreground print:hidden"
            >
                Payment attempts are session-bound and expire. Payer mobile
                numbers are evidence only and never select the receiving
                Account.
            </p>
        </div>
    </ClaimStepShell>
</template>
