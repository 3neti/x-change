<script setup lang="ts">
import {
    Check,
    ExternalLink,
    LoaderCircle,
    Minimize2,
    ScanQrCode,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { PayCodeCostEstimate } from '../../composables/usePayCodeCostEstimate';
import type {
    RiderStampPreview,
    RiderStampPreviewSource,
} from '../riderStampPreview';
import CockpitPayCodeCanvas from './CockpitPayCodeCanvas.vue';
import CockpitPayCodeShareCard from './CockpitPayCodeShareCard.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        code?: string | null;
        amount: string | number;
        currency: string;
        recipient?: string;
        purpose?: string;
        claimOutcome: 'provider_disbursement' | 'account_funding';
        voucherType: 'redeemable' | 'payable' | 'settlement';
        expiry?: string;
        instructionKeys?: string[];
        hasRiderDesign?: boolean;
        riderDesignSource?: RiderStampPreviewSource;
        riderDesignDocument?: string;
        riderStamp?: RiderStampPreview | null;
        claimQr?: string | null;
        claimUrl?: string | null;
        shareCardUrl?: string | null;
        detailUrl?: string | null;
        collectionAttemptUrl?: string | null;
        paymentUrl?: string | null;
        costEstimate?: PayCodeCostEstimate | null;
        quantity?: string | number;
    }>(),
    {
        code: null,
        recipient: '',
        purpose: '',
        expiry: 'No expiry',
        instructionKeys: () => [],
        hasRiderDesign: false,
        riderDesignSource: 'default',
        riderDesignDocument: '',
        riderStamp: null,
        claimQr: null,
        claimUrl: null,
        shareCardUrl: null,
        detailUrl: null,
        collectionAttemptUrl: null,
        paymentUrl: null,
        costEstimate: null,
        quantity: 1,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const dialog = ref<HTMLElement | null>(null);
const showStampButton = ref<HTMLButtonElement | null>(null);
const qrExpanded = ref(false);
const paymentQr = ref<string | null>(null);
const paymentQrStatus = ref<'idle' | 'loading' | 'ready' | 'failed'>('idle');
const paymentQrMessage = ref('');
const loadedPaymentCode = ref<string | null>(null);
let returnFocus: HTMLElement | null = null;
let qrTriggerTestId: string | null = null;

const normalizedCode = computed<string>(() => props.code?.trim() || 'Pay Code');
const normalizedClaimUrl = computed<string | null>(() => {
    const value = props.claimUrl?.trim();

    return value ? value : null;
});
const normalizedClaimQr = computed<string | null>(() => {
    const value = props.claimQr?.trim();

    return value ? value : null;
});
const normalizedShareCardUrl = computed<string | null>(() => {
    const value = props.shareCardUrl?.trim();

    return value ? value : null;
});
const formattedAmount = computed<string>(() => {
    const amount = Number(props.amount);

    if (!Number.isFinite(amount)) {
        return `${props.currency || 'PHP'} 0.00`;
    }

    return `${props.currency || 'PHP'} ${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
});
// Safe, already-authorized presentation context only — never raw claim or
// instruction data. Passed to the reusable share card as display text; it
// never becomes part of the composed share message itself.
const shareContextText = computed<string>(() => {
    const action =
        props.claimOutcome === 'account_funding'
            ? 'Add this Pay Code to your Account'
            : 'Claim this Pay Code';

    return `${action} \u00b7 ${formattedAmount.value}`;
});
const isCollectible = computed<boolean>(() => {
    return props.voucherType === 'payable' || props.voucherType === 'settlement';
});

watch(
    () => props.open,
    async (open): Promise<void> => {
        qrExpanded.value = false;

        if (!open) {
            return;
        }

        returnFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        await nextTick();
        dialog.value?.focus();

        if (isCollectible.value) {
            void loadPaymentQr();
        }
    },
    { immediate: true },
);

async function loadPaymentQr(): Promise<void> {
    if (
        !props.collectionAttemptUrl ||
        loadedPaymentCode.value === normalizedCode.value
    ) {
        return;
    }

    paymentQrStatus.value = 'loading';
    paymentQrMessage.value = '';

    try {
        const response = await fetch(props.collectionAttemptUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = (await response.json().catch(() => ({}))) as Record<
            string,
            unknown
        >;
        const payload = nestedString(body, [
            'attempt',
            'qr_code',
            'base64_payload',
        ]);
        const mimeType =
            nestedString(body, ['attempt', 'qr_code', 'mime_type']) ??
            'image/png';

        if (!response.ok || !payload) {
            throw new Error('Payment QR unavailable');
        }

        paymentQr.value = payload.startsWith('data:')
            ? payload
            : `data:${mimeType};base64,${payload}`;
        loadedPaymentCode.value = normalizedCode.value;
        paymentQrStatus.value = 'ready';
    } catch {
        paymentQrStatus.value = 'failed';
        paymentQrMessage.value =
            'Payment QR is temporarily unavailable. The public payment page remains available.';
    }
}

function csrfHeader(): Record<string, string> {
    const token = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

function nestedString(
    value: Record<string, unknown>,
    path: string[],
): string | null {
    let current: unknown = value;

    for (const key of path) {
        if (typeof current !== 'object' || current === null) {
            return null;
        }

        current = (current as Record<string, unknown>)[key];
    }

    return typeof current === 'string' && current.trim() !== ''
        ? current.trim()
        : null;
}

onBeforeUnmount((): void => {
    returnFocus = null;
    qrTriggerTestId = null;
});

function close(): void {
    emit('close');
    nextTick((): void => {
        returnFocus?.focus();
        returnFocus = null;
    });
}

async function showQr(): Promise<void> {
    if (normalizedClaimQr.value) {
        qrTriggerTestId =
            document.activeElement instanceof HTMLElement
                ? (document.activeElement.dataset.testid ?? null)
                : null;
        qrExpanded.value = true;
        await nextTick();
        showStampButton.value?.focus();
    }
}

async function showStamp(): Promise<void> {
    qrExpanded.value = false;
    await nextTick();
    const selector = qrTriggerTestId
        ? `[data-testid="${qrTriggerTestId}"]`
        : null;
    const trigger = selector
        ? dialog.value?.querySelector<HTMLElement>(selector)
        : null;

    trigger?.focus();
    qrTriggerTestId = null;
}

function handleEscape(): void {
    if (qrExpanded.value) {
        showStamp();

        return;
    }

    close();
}
</script>

<template>
    <Teleport v-if="open" to="body">
        <div
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/65 p-3 backdrop-blur-sm sm:p-6"
            data-testid="cockpit-issued-pay-code-dialog-backdrop"
            @click.self="close"
            @keydown.esc.prevent="handleEscape"
        >
            <section
                ref="dialog"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="issued-pay-code-dialog-title"
                aria-describedby="issued-pay-code-dialog-description"
                class="my-auto w-full max-w-6xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl outline-none dark:border-slate-800 dark:bg-slate-950"
                data-testid="cockpit-issued-pay-code-dialog"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6 dark:border-slate-800"
                >
                    <div>
                        <div
                            class="flex flex-wrap items-center gap-2 text-emerald-700 dark:text-emerald-300"
                        >
                            <Check class="size-5" aria-hidden="true" />
                            <p
                                class="text-xs font-bold uppercase tracking-[0.16em]"
                            >
                                Issued Successfully
                            </p>
                        </div>
                        <h2
                            id="issued-pay-code-dialog-title"
                            class="mt-1 text-xl font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            Pay Code {{ normalizedCode }} Is Ready
                        </h2>
                        <p
                            id="issued-pay-code-dialog-description"
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Review both sides, then share the secure claim link.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 dark:border-slate-800 dark:hover:bg-slate-900 dark:hover:text-white"
                        aria-label="Close Pay Code"
                        data-testid="cockpit-issued-pay-code-dialog-close"
                        @click="close"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </header>

                <div
                    class="grid gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_17rem]"
                >
                    <div
                        v-if="qrExpanded && normalizedClaimQr"
                        class="flex min-h-80 flex-col rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80 sm:min-h-[38rem] lg:col-span-2"
                        data-testid="cockpit-issued-pay-code-expanded-qr"
                    >
                        <div
                            class="mb-3 flex items-start justify-between gap-3 px-1"
                        >
                            <div>
                                <p
                                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                                >
                                    Claim QR
                                </p>
                                <p
                                    class="text-xs text-slate-600 dark:text-slate-300"
                                >
                                    Scan to open Pay Code {{ normalizedCode }}.
                                </p>
                            </div>
                            <button
                                ref="showStampButton"
                                type="button"
                                class="inline-flex min-h-9 shrink-0 items-center justify-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-900"
                                data-testid="cockpit-issued-pay-code-show-stamp"
                                @click="showStamp"
                            >
                                <Minimize2
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Show Stamp
                            </button>
                        </div>
                        <button
                            type="button"
                            class="group flex min-h-80 flex-1 items-center justify-center overflow-hidden rounded-[1.4rem] bg-white p-4 shadow-xl shadow-slate-900/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:min-h-[34rem] sm:p-6"
                            aria-label="Show finalized Stamp"
                            aria-pressed="true"
                            data-testid="cockpit-issued-pay-code-expanded-qr-button"
                            @click="showStamp"
                        >
                            <img
                                :src="normalizedClaimQr"
                                :alt="`Claim QR for Pay Code ${normalizedCode}`"
                                class="aspect-square w-full max-w-2xl object-contain"
                                width="1024"
                                height="1024"
                                decoding="async"
                                data-testid="cockpit-issued-pay-code-expanded-qr-image"
                            />
                        </button>
                    </div>

                    <div
                        v-else-if="normalizedShareCardUrl"
                        class="rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80"
                        data-testid="cockpit-issued-pay-code-artifact"
                    >
                        <div class="mb-3 px-1">
                            <p
                                class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                Finalized Stamp
                            </p>
                            <p
                                class="text-xs text-slate-600 dark:text-slate-300"
                            >
                                Tap the QR in the Stamp to enlarge it.
                            </p>
                        </div>
                        <button
                            v-if="normalizedClaimQr"
                            type="button"
                            class="group relative block w-full overflow-hidden rounded-[1.4rem] bg-slate-950 shadow-xl shadow-slate-900/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            :aria-label="`Enlarge claim QR for Pay Code ${normalizedCode}`"
                            aria-pressed="false"
                            data-testid="cockpit-issued-pay-code-artifact-qr-button"
                            @click="showQr"
                        >
                            <img
                                :src="normalizedShareCardUrl"
                                :alt="`Finalized Pay Code ${normalizedCode}`"
                                class="aspect-[1200/630] w-full object-contain"
                                width="1200"
                                height="630"
                                loading="eager"
                                decoding="async"
                                data-testid="cockpit-issued-pay-code-artifact-image"
                            />
                            <span
                                class="pointer-events-none absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-slate-950/85 px-3 py-1.5 text-xs font-semibold text-white opacity-90 shadow-lg backdrop-blur-sm transition group-hover:bg-emerald-700 group-focus-visible:bg-emerald-700"
                            >
                                <ScanQrCode class="size-4" aria-hidden="true" />
                                Enlarge QR
                            </span>
                        </button>
                        <img
                            v-else
                            :src="normalizedShareCardUrl"
                            :alt="`Finalized Pay Code ${normalizedCode}`"
                            class="aspect-[1200/630] w-full rounded-[1.4rem] bg-slate-950 object-contain shadow-xl shadow-slate-900/20"
                            width="1200"
                            height="630"
                            loading="eager"
                            decoding="async"
                            data-testid="cockpit-issued-pay-code-artifact-image"
                        />
                    </div>

                    <CockpitPayCodeCanvas
                        v-else
                        :amount="amount"
                        :currency="currency"
                        :recipient="recipient"
                        :purpose="purpose"
                        :claim-outcome="claimOutcome"
                        :voucher-type="voucherType"
                        :expiry="expiry"
                        :instruction-keys="instructionKeys"
                        :issued-code="code"
                        :has-rider-design="hasRiderDesign"
                        :rider-design-source="riderDesignSource"
                        :rider-design-document="riderDesignDocument"
                        :rider-stamp="riderStamp"
                        :claim-qr="claimQr"
                        interactive-claim-qr
                        :cost-estimate="costEstimate"
                        :quantity="quantity"
                        presentation="finalized"
                        @claim-qr-click="showQr"
                    />

                    <aside
                        v-if="!qrExpanded"
                        class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        data-testid="cockpit-issued-pay-code-sharing-sidebar"
                    >
                        <section
                            v-if="isCollectible"
                            class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-900/70 dark:bg-sky-950/30"
                            data-testid="cockpit-issued-pay-code-payment"
                        >
                            <p
                                class="text-xs font-bold uppercase tracking-[0.14em] text-sky-800 dark:text-sky-200"
                            >
                                Payment QR
                            </p>
                            <p class="mt-1 text-xs text-sky-900 dark:text-sky-100">
                                For the payer to scan. This is separate from the claim QR.
                            </p>
                            <div
                                v-if="paymentQrStatus === 'loading'"
                                class="mt-3 flex min-h-28 items-center justify-center gap-2 text-xs text-sky-800 dark:text-sky-200"
                                data-testid="cockpit-issued-pay-code-payment-loading"
                            >
                                <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
                                Preparing QR Ph…
                            </div>
                            <img
                                v-else-if="paymentQrStatus === 'ready' && paymentQr"
                                :src="paymentQr"
                                :alt="`Payment QR for Pay Code ${normalizedCode}`"
                                class="mx-auto mt-3 aspect-square w-full max-w-48 rounded-xl bg-white object-contain p-2"
                                data-testid="cockpit-issued-pay-code-payment-qr"
                            />
                            <p
                                v-else-if="paymentQrStatus === 'failed'"
                                class="mt-3 text-xs leading-5 text-amber-800 dark:text-amber-200"
                                role="status"
                                data-testid="cockpit-issued-pay-code-payment-error"
                            >
                                {{ paymentQrMessage }}
                            </p>
                            <a
                                v-if="paymentUrl"
                                :href="paymentUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-semibold text-sky-900 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-slate-950 dark:text-sky-100 dark:hover:bg-sky-950"
                                data-testid="cockpit-issued-pay-code-payment-link"
                            >
                                <ExternalLink class="size-3.5" aria-hidden="true" />
                                Open public payment page
                            </a>
                        </section>

                        <CockpitPayCodeShareCard
                            :code="normalizedCode"
                            :claim-url="normalizedClaimUrl"
                            :claim-qr="claimQr"
                            :context-text="shareContextText"
                            variant="compact"
                        />

                        <a
                            v-if="detailUrl"
                            :href="detailUrl"
                            class="mt-auto inline-flex min-h-11 w-full items-center justify-center gap-2 border-t border-slate-200 pt-4 text-sm font-semibold text-slate-700 transition hover:text-emerald-700 dark:border-slate-800 dark:text-slate-200 dark:hover:text-emerald-300"
                            data-testid="cockpit-issued-pay-code-detail"
                        >
                            <ExternalLink class="size-4" aria-hidden="true" />
                            View Pay Code Details
                        </a>
                    </aside>
                </div>
            </section>
        </div>
    </Teleport>
</template>
