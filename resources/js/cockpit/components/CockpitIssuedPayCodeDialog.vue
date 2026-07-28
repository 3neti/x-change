<script setup lang="ts">
import {
    Check,
    Copy,
    ExternalLink,
    Mail,
    MessageCircle,
    Share2,
    Smartphone,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { PayCodeCostEstimate } from '../../composables/usePayCodeCostEstimate';
import type { RiderOgPreviewSource } from '../riderOgPreview';
import CockpitPayCodeCanvas from './CockpitPayCodeCanvas.vue';

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
        instructionLabels?: string[];
        hasRiderDesign?: boolean;
        riderDesignSource?: RiderOgPreviewSource;
        riderDesignDocument?: string;
        claimUrl?: string | null;
        detailUrl?: string | null;
        costEstimate?: PayCodeCostEstimate | null;
        quantity?: string | number;
    }>(),
    {
        code: null,
        recipient: '',
        purpose: '',
        expiry: 'No expiry',
        instructionLabels: () => [],
        hasRiderDesign: false,
        riderDesignSource: 'default',
        riderDesignDocument: '',
        claimUrl: null,
        detailUrl: null,
        costEstimate: null,
        quantity: 1,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const dialog = ref<HTMLElement | null>(null);
const copyState = ref<'idle' | 'copied' | 'failed'>('idle');
let returnFocus: HTMLElement | null = null;

const normalizedCode = computed<string>(() => props.code?.trim() || 'Pay Code');
const normalizedClaimUrl = computed<string | null>(() => {
    const value = props.claimUrl?.trim();

    return value ? value : null;
});
const shareValue = computed<string>(() => {
    return normalizedClaimUrl.value ?? normalizedCode.value;
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
const shareTitle = computed<string>(() => {
    return `Pay Code ${normalizedCode.value}`;
});
const shareMessage = computed<string>(() => {
    const action =
        props.claimOutcome === 'account_funding'
            ? 'Add this Pay Code to your Account'
            : 'Claim this Pay Code';

    return `${action}: ${normalizedCode.value} (${formattedAmount.value})\n${shareValue.value}`;
});
const encodedMessage = computed<string>(() =>
    encodeURIComponent(shareMessage.value),
);
const encodedSubject = computed<string>(() =>
    encodeURIComponent(shareTitle.value),
);
const canNativeShare = computed<boolean>(() => {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function'
    );
});
const whatsappUrl = computed<string>(
    () => `https://wa.me/?text=${encodedMessage.value}`,
);
const smsUrl = computed<string>(() => `sms:?body=${encodedMessage.value}`);
const emailUrl = computed<string>(
    () =>
        `mailto:?subject=${encodedSubject.value}&body=${encodedMessage.value}`,
);

watch(
    () => props.open,
    async (open): Promise<void> => {
        if (!open) {
            copyState.value = 'idle';

            return;
        }

        returnFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        await nextTick();
        dialog.value?.focus();
    },
);

onBeforeUnmount((): void => {
    returnFocus = null;
});

function close(): void {
    emit('close');
    nextTick((): void => {
        returnFocus?.focus();
        returnFocus = null;
    });
}

async function shareNative(): Promise<void> {
    if (!canNativeShare.value) {
        return;
    }

    try {
        await navigator.share({
            title: shareTitle.value,
            text: shareMessage.value,
            ...(normalizedClaimUrl.value
                ? { url: normalizedClaimUrl.value }
                : {}),
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }
    }
}

async function copyClaimLink(): Promise<void> {
    const clipboard = globalThis.navigator?.clipboard;

    if (!clipboard?.writeText) {
        copyState.value = 'failed';

        return;
    }

    try {
        await clipboard.writeText(shareValue.value);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'failed';
    }
}
</script>

<template>
    <Teleport v-if="open" to="body">
        <div
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/65 p-3 backdrop-blur-sm sm:p-6"
            data-testid="cockpit-issued-pay-code-dialog-backdrop"
            @click.self="close"
            @keydown.esc.prevent="close"
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
                                class="text-xs font-bold tracking-[0.16em] uppercase"
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
                    <CockpitPayCodeCanvas
                        :amount="amount"
                        :currency="currency"
                        :recipient="recipient"
                        :purpose="purpose"
                        :claim-outcome="claimOutcome"
                        :voucher-type="voucherType"
                        :expiry="expiry"
                        :instruction-labels="instructionLabels"
                        :issued-code="code"
                        :has-rider-design="hasRiderDesign"
                        :rider-design-source="riderDesignSource"
                        :rider-design-document="riderDesignDocument"
                        :cost-estimate="costEstimate"
                        :quantity="quantity"
                        presentation="finalized"
                    />

                    <aside
                        class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                    >
                        <div>
                            <p
                                class="text-xs font-bold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Share Pay Code
                            </p>
                            <p
                                class="mt-2 font-mono text-sm font-bold break-all text-slate-950 dark:text-white"
                            >
                                {{ normalizedCode }}
                            </p>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button
                                v-if="canNativeShare"
                                type="button"
                                class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                data-testid="cockpit-issued-pay-code-native-share"
                                @click="shareNative"
                            >
                                <Share2 class="size-4" aria-hidden="true" />
                                Share
                            </button>

                            <a
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="cockpit-issued-pay-code-whatsapp"
                            >
                                <MessageCircle
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                WhatsApp
                            </a>
                            <a
                                :href="smsUrl"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="cockpit-issued-pay-code-sms"
                            >
                                <Smartphone class="size-4" aria-hidden="true" />
                                SMS
                            </a>
                            <a
                                :href="emailUrl"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="cockpit-issued-pay-code-email"
                            >
                                <Mail class="size-4" aria-hidden="true" />
                                Email
                            </a>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="cockpit-issued-pay-code-copy"
                                @click="copyClaimLink"
                            >
                                <Check
                                    v-if="copyState === 'copied'"
                                    class="size-4 text-emerald-600"
                                    aria-hidden="true"
                                />
                                <Copy
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    copyState === 'copied'
                                        ? 'Copied'
                                        : 'Copy Link'
                                }}
                            </button>
                        </div>

                        <p
                            v-if="copyState === 'failed'"
                            class="mt-2 text-xs text-rose-600 dark:text-rose-300"
                            role="status"
                        >
                            Copy is unavailable in this browser.
                        </p>

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
