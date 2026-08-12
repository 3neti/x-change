<script setup lang="ts">
import { Check, ExternalLink, X } from 'lucide-vue-next';
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
        costEstimate: null,
        quantity: 1,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const dialog = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

const normalizedCode = computed<string>(() => props.code?.trim() || 'Pay Code');
const normalizedClaimUrl = computed<string | null>(() => {
    const value = props.claimUrl?.trim();

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

watch(
    () => props.open,
    async (open): Promise<void> => {
        if (!open) {
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
                    <div
                        v-if="normalizedShareCardUrl"
                        class="rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80"
                        data-testid="cockpit-issued-pay-code-artifact"
                    >
                        <div class="mb-3 px-1">
                            <p
                                class="text-[0.65rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                Finalized Stamp
                            </p>
                            <p
                                class="text-xs text-slate-600 dark:text-slate-300"
                            >
                                Exact image used when the claim link is shared.
                            </p>
                        </div>
                        <img
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
                        :cost-estimate="costEstimate"
                        :quantity="quantity"
                        presentation="finalized"
                    />

                    <aside
                        class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                    >
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
