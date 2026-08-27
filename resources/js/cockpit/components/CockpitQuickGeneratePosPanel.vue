<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';
import {
    CheckCircle2,
    LoaderCircle,
    QrCode,
    ReceiptText,
    RotateCcw,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type {
    CockpitPosPaymentAttempt,
    CockpitQuickGenerateMutationContract,
    CockpitVoucherReadModel,
} from '../types';

const props = withDefaults(
    defineProps<{
        mutationContract?: CockpitQuickGenerateMutationContract;
        posVoucher?: CockpitVoucherReadModel | null;
        currentUserWalletId?: string | number | null;
    }>(),
    {
        mutationContract: undefined,
        posVoucher: null,
        currentUserWalletId: null,
    },
);

type PosStage =
    | 'entry'
    | 'issuing'
    | 'preparing_qr'
    | 'awaiting_payment'
    | 'paid';

const amount = ref('');
const externalReference = ref('');
const stage = ref<PosStage>('entry');
const errorMessage = ref<string | null>(null);
const issuedCode = ref<string | null>(null);
const paymentUrl = ref<string | null>(null);
const qrSource = ref<string | null>(null);
const confirmedAt = ref<string | null>(null);

const routeUrl = computed<string | null>(() => {
    const value = props.mutationContract?.route_url;

    return typeof value === 'string' && value.trim() !== ''
        ? value.trim()
        : null;
});

const normalizedAmount = computed<number>(() => Number(amount.value));
const canGenerate = computed<boolean>(() => {
    return (
        routeUrl.value !== null &&
        props.currentUserWalletId !== null &&
        props.currentUserWalletId !== undefined &&
        Number.isFinite(normalizedAmount.value) &&
        normalizedAmount.value > 0 &&
        externalReference.value.trim() !== '' &&
        stage.value === 'entry'
    );
});

const activeVoucher = computed<CockpitVoucherReadModel | null>(() => {
    if (
        issuedCode.value === null ||
        props.posVoucher?.code?.toUpperCase() !== issuedCode.value.toUpperCase()
    ) {
        return null;
    }

    return props.posVoucher;
});

const collection = computed(() => activeVoucher.value?.collection ?? null);
const isFullyCollected = computed<boolean>(
    () => collection.value?.is_fully_collected === true,
);
const consumerStatus = computed<string>(
    () => collection.value?.consumer_status ?? 'payable',
);
const collectedAmount = computed<string>(() =>
    money(
        collection.value?.collected_total_minor ?? 0,
        collection.value?.currency ?? 'PHP',
    ),
);
const saleAmount = computed<string>(() =>
    money(Math.round((normalizedAmount.value || 0) * 100), 'PHP'),
);
const confirmationTime = computed<string | null>(() => {
    if (confirmedAt.value === null) {
        return null;
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(confirmedAt.value));
});

const { start: startStatusPoll, stop: stopStatusPoll } = usePoll(
    5000,
    () => ({
        data: issuedCode.value === null ? {} : { pos_code: issuedCode.value },
        only: ['pos_voucher'],
        preserveScroll: true,
        preserveState: true,
    }),
    {
        autoStart: false,
        mode: 'rest',
    },
);

watch(isFullyCollected, (paid) => {
    if (!paid || issuedCode.value === null) {
        return;
    }

    confirmedAt.value ??= new Date().toISOString();
    stage.value = 'paid';
    stopStatusPoll();
});

async function generateQr(): Promise<void> {
    if (!canGenerate.value || routeUrl.value === null) {
        return;
    }

    stage.value = 'issuing';
    errorMessage.value = null;

    try {
        const issuanceResponse = await fetch(routeUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'Idempotency-Key': newIdempotencyKey(),
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify(issuancePayload()),
        });
        const issuance = await safeJson(issuanceResponse);

        if (!issuanceResponse.ok) {
            throw new Error(
                responseMessage(issuance, 'The sale could not be created.'),
            );
        }

        const code = nestedString(issuance, ['result', 'code']);
        const collectionAttemptUrl = nestedString(issuance, [
            'result',
            'links',
            'collection_attempt',
        ]);

        paymentUrl.value = nestedString(issuance, [
            'result',
            'links',
            'payment',
        ]);

        if (code === null || collectionAttemptUrl === null) {
            throw new Error(
                'The Pay Code was issued without a usable payment handoff.',
            );
        }

        issuedCode.value = code;
        stage.value = 'preparing_qr';

        const attemptResponse = await fetch(collectionAttemptUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const attemptBody = await safeJson(attemptResponse);
        const attempt = nestedRecord(attemptBody, [
            'attempt',
        ]) as CockpitPosPaymentAttempt | null;
        const qrPayload = nestedString(attemptBody, [
            'attempt',
            'qr_code',
            'base64_payload',
        ]);
        const mimeType =
            nestedString(attemptBody, ['attempt', 'qr_code', 'mime_type']) ??
            'image/png';

        if (!attemptResponse.ok || attempt === null || qrPayload === null) {
            throw new Error(
                responseMessage(
                    attemptBody,
                    'The Pay Code was issued, but its payment QR is temporarily unavailable.',
                ),
            );
        }

        qrSource.value = qrPayload.startsWith('data:')
            ? qrPayload
            : `data:${mimeType};base64,${qrPayload}`;
        stage.value = 'awaiting_payment';
        startStatusPoll();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'The sale could not be prepared.';
        stage.value = issuedCode.value === null ? 'entry' : 'preparing_qr';

        if (issuedCode.value !== null) {
            startStatusPoll();
        }
    }
}

function newSale(): void {
    stopStatusPoll();
    amount.value = '';
    externalReference.value = '';
    issuedCode.value = null;
    paymentUrl.value = null;
    qrSource.value = null;
    confirmedAt.value = null;
    errorMessage.value = null;
    stage.value = 'entry';
}

function issuancePayload(): Record<string, unknown> {
    return {
        cash: {
            amount: normalizedAmount.value,
            currency: 'PHP',
            validation: {
                country: 'PH',
            },
        },
        inputs: {
            fields: [],
            requirements: [],
        },
        feedback: {
            email: null,
            mobile: null,
            webhook: null,
        },
        rider: {
            message: null,
            url: null,
            redirect_timeout: null,
            splash: null,
            splash_timeout: null,
            og_source: null,
        },
        count: 1,
        voucher_type: 'payable',
        target_amount: normalizedAmount.value,
        metadata: {
            collection_wallet_id: props.currentUserWalletId,
            custom: {
                external_reference: externalReference.value.trim(),
                cockpit: {
                    source: 'cockpit.quick-generate',
                    builder: 'pos',
                    payee: {
                        kind: 'open',
                        explicit_secret: false,
                    },
                },
            },
        },
    };
}

function money(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);
}

function newIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `cockpit-pos-${Date.now()}-${Math.random().toString(36).slice(2)}`;
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

function nestedRecord(
    value: Record<string, unknown>,
    path: string[],
): Record<string, unknown> | null {
    let current: unknown = value;

    for (const key of path) {
        if (typeof current !== 'object' || current === null) {
            return null;
        }

        current = (current as Record<string, unknown>)[key];
    }

    return typeof current === 'object' && current !== null
        ? (current as Record<string, unknown>)
        : null;
}

function nestedString(
    value: Record<string, unknown>,
    path: string[],
): string | null {
    const result = path.reduce<unknown>((current, key) => {
        if (typeof current !== 'object' || current === null) {
            return null;
        }

        return (current as Record<string, unknown>)[key];
    }, value);

    return typeof result === 'string' && result.trim() !== ''
        ? result.trim()
        : null;
}

function responseMessage(
    body: Record<string, unknown>,
    fallback: string,
): string {
    return nestedString(body, ['message']) ?? fallback;
}
</script>

<template>
    <section
        class="mx-auto w-full max-w-3xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
        data-testid="cockpit-quick-generate-pos-panel"
    >
        <div
            v-if="stage === 'entry' || stage === 'issuing'"
            class="space-y-6 p-5 sm:p-8"
        >
            <header class="space-y-2 text-center">
                <div
                    class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    <ReceiptText class="size-6" aria-hidden="true" />
                </div>
                <h3
                    class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                >
                    New sale
                </h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Enter the amount and your order reference. The customer pays
                    by QR Ph.
                </p>
            </header>

            <form class="space-y-5" @submit.prevent="generateQr">
                <label
                    class="grid gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Amount to collect
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-2xl font-semibold text-slate-500"
                            >₱</span
                        >
                        <input
                            v-model="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            autocomplete="off"
                            class="h-20 w-full rounded-2xl border border-slate-300 bg-white pr-4 pl-12 text-4xl font-semibold tracking-tight text-slate-950 shadow-sm transition outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:focus:ring-emerald-950"
                            placeholder="0.00"
                            data-testid="cockpit-pos-amount"
                        />
                    </div>
                </label>

                <label
                    class="grid gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Reference or description
                    <input
                        v-model="externalReference"
                        type="text"
                        maxlength="190"
                        autocomplete="off"
                        class="h-14 w-full rounded-2xl border border-slate-300 bg-white px-4 text-base text-slate-950 shadow-sm transition outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:focus:ring-emerald-950"
                        placeholder="Order 1042 · Snacks"
                        data-testid="cockpit-pos-reference"
                    />
                </label>

                <p
                    v-if="errorMessage"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                    data-testid="cockpit-pos-error"
                >
                    {{ errorMessage }}
                </p>

                <button
                    type="submit"
                    :disabled="!canGenerate"
                    class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    data-testid="cockpit-pos-generate"
                >
                    <LoaderCircle
                        v-if="stage === 'issuing'"
                        class="size-5 animate-spin"
                        aria-hidden="true"
                    />
                    <QrCode v-else class="size-5" aria-hidden="true" />
                    {{ stage === 'issuing' ? 'Creating sale…' : 'Generate QR' }}
                </button>
            </form>
        </div>

        <div
            v-else-if="stage !== 'paid'"
            class="space-y-5 p-5 text-center sm:p-8"
        >
            <header class="space-y-1">
                <p
                    class="text-xs font-bold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Scan to pay
                </p>
                <h3
                    class="text-3xl font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ saleAmount }}
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ externalReference }}
                </p>
            </header>

            <div
                class="mx-auto grid aspect-square w-full max-w-md place-items-center rounded-3xl border border-slate-200 bg-white p-4 shadow-inner dark:border-slate-700"
            >
                <img
                    v-if="qrSource"
                    :src="qrSource"
                    alt="QR Ph payment code"
                    class="size-full object-contain"
                    data-testid="cockpit-pos-payment-qr"
                />
                <LoaderCircle
                    v-else
                    class="size-12 animate-spin text-emerald-600"
                    aria-hidden="true"
                />
            </div>

            <div
                class="flex items-center justify-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                role="status"
                data-testid="cockpit-pos-status"
            >
                <span class="relative flex size-3">
                    <span
                        class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"
                    ></span>
                    <span
                        class="relative inline-flex size-3 rounded-full bg-emerald-500"
                    ></span>
                </span>
                Waiting for payment · {{ consumerStatus }}
            </div>

            <p
                v-if="errorMessage"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                role="alert"
                data-testid="cockpit-pos-error"
            >
                {{ errorMessage }}
                <a
                    v-if="paymentUrl"
                    :href="paymentUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ml-1 font-semibold underline"
                    >Open payment page</a
                >
            </p>
        </div>

        <div
            v-else
            class="space-y-6 p-6 text-center sm:p-10"
            data-testid="cockpit-pos-paid"
        >
            <div
                class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
            >
                <CheckCircle2 class="size-11" aria-hidden="true" />
            </div>
            <div class="space-y-2">
                <p
                    class="text-sm font-bold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Paid
                </p>
                <h3
                    class="text-4xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                >
                    {{ collectedAmount }}
                </h3>
                <p class="text-base text-slate-600 dark:text-slate-300">
                    {{ externalReference }}
                </p>
                <p
                    v-if="confirmationTime"
                    class="text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-pos-confirmed-at"
                >
                    Confirmed on this screen {{ confirmationTime }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 text-base font-semibold text-white transition hover:bg-slate-800 sm:w-auto sm:min-w-56 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                data-testid="cockpit-pos-new-sale"
                @click="newSale"
            >
                <RotateCcw class="size-5" aria-hidden="true" />
                New Sale
            </button>
        </div>
    </section>
</template>
