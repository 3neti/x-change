<script setup lang="ts">
import {
    ArrowLeftRight,
    Palette,
    QrCode,
    ReceiptText,
    Route,
    UserRound,
} from 'lucide-vue-next';
import { computed, useId, useSlots, watchEffect } from 'vue';
import type {
    PayCodeCostCharge,
    PayCodeCostEstimate,
} from '../../composables/usePayCodeCostEstimate';
import {
    payCodeOutcomeIndicatorKey,
    resolvePayCodeIndicator,
} from '../payCodeIndicators';
import type {
    RiderStampPreview,
    RiderStampPreviewSource,
} from '../riderStampPreview';
import CockpitPayCodeIndicator from './CockpitPayCodeIndicator.vue';

const props = withDefaults(
    defineProps<{
        amount: string | number;
        currency: string;
        recipient?: string;
        purpose?: string;
        claimOutcome: 'provider_disbursement' | 'account_funding';
        voucherType: 'redeemable' | 'payable' | 'settlement';
        expiry?: string;
        instructionKeys?: string[];
        issuedCode?: string | null;
        hasRiderDesign?: boolean;
        riderDesignSource?: RiderStampPreviewSource;
        riderDesignDocument?: string;
        riderStamp?: RiderStampPreview | null;
        claimQr?: string | null;
        interactiveClaimQr?: boolean;
        presentation?: 'live' | 'finalized';
        costEstimate?: PayCodeCostEstimate | null;
        costLoading?: boolean;
        costError?: string | null;
        quantity?: string | number;
    }>(),
    {
        recipient: '',
        purpose: '',
        expiry: 'No expiry',
        instructionKeys: () => [],
        issuedCode: null,
        hasRiderDesign: false,
        riderDesignSource: 'default',
        riderDesignDocument: '',
        riderStamp: null,
        claimQr: null,
        interactiveClaimQr: false,
        presentation: 'live',
        costEstimate: null,
        costLoading: false,
        costError: null,
        quantity: 1,
    },
);

const emit = defineEmits<{
    claimQrClick: [];
}>();

type PayCodeCanvasView = 'stamp' | 'design' | 'claim' | 'cost';

const visibleView = defineModel<PayCodeCanvasView>('view', {
    default: 'stamp',
});
const slots = useSlots();
const viewId = useId();
const hasDesignView = computed<boolean>(() => {
    return props.presentation === 'live' && slots.design !== undefined;
});
const hasClaimView = computed<boolean>(() => {
    return props.presentation === 'live' && slots.claim !== undefined;
});
const payCodeMarkUrl = '/vendor/x-change/images/pay-code/pay-code-mark.svg';

watchEffect(() => {
    if (visibleView.value === 'design' && !hasDesignView.value) {
        visibleView.value = 'stamp';
    }

    if (visibleView.value === 'claim' && !hasClaimView.value) {
        visibleView.value = 'stamp';
    }
});

function tabId(view: PayCodeCanvasView): string {
    return `${viewId}-${view}-tab`;
}

function panelId(view: PayCodeCanvasView): string {
    return `${viewId}-${view}-panel`;
}

const formattedAmount = computed<string>(() => {
    const value = Number(props.amount);
    const amount = Number.isFinite(value) ? value : 0;
    const formattedValue = amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const currency = props.currency.trim().toUpperCase() || 'PHP';

    if (currency === 'PHP') {
        return `₱${formattedValue}`;
    }

    return `${currency} ${formattedValue}`;
});

const recipientLabel = computed<string>(() => {
    const recipient = props.recipient.trim();

    if (recipient === '' || recipient.toUpperCase() === 'CASH') {
        return 'Anyone with this Pay Code';
    }

    if (/^(\+|09|639|63)/.test(recipient)) {
        return `Mobile ending ${recipient.slice(-4)}`;
    }

    return recipient;
});

const stampIndicatorKeys = computed<string[]>(() => {
    return [
        ...new Set([
            payCodeOutcomeIndicatorKey(props.claimOutcome, props.voucherType),
            ...props.instructionKeys,
        ]),
    ];
});

const visibleStampIndicatorKeys = computed<string[]>(() => {
    return stampIndicatorKeys.value.slice(0, 6);
});

const hiddenStampIndicators = computed(() => {
    return stampIndicatorKeys.value
        .slice(6)
        .map((key) => resolvePayCodeIndicator(key));
});

const hiddenStampIndicatorTooltip = computed<string>(() => {
    return hiddenStampIndicators.value
        .map((indicator) => indicator.label)
        .join(', ');
});

const displayedCode = computed<string>(() => {
    return props.issuedCode?.trim() || 'PAY CODE PREVIEW';
});

const riderArtworkScrimClass = computed<string>(() => {
    return 'from-slate-950/70 via-slate-950/25 to-transparent';
});

const stampDesignClass = computed<string>(() => {
    if (props.riderStamp?.design.id === 'x-change-amber') {
        return 'border-orange-700 bg-gradient-to-br from-orange-950 via-orange-900 to-amber-800 text-white';
    }

    if (props.riderStamp?.design.id === 'x-change-steampunk') {
        return 'border-amber-800 bg-gradient-to-br from-[#312214] via-[#49341f] to-[#78532d] text-amber-50';
    }

    return 'border-slate-700 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-700 text-white';
});

const riderArtworkFrameClass = computed<string>(() => {
    return props.riderStamp === null && props.riderDesignSource === 'message'
        ? 'opacity-60'
        : 'opacity-100';
});

const showStampLogo = computed<boolean>(() => {
    return props.riderStamp?.composition.showLogo !== false;
});

const showStampTagline = computed<boolean>(() => {
    return props.riderStamp?.composition.showTagline !== false;
});

const showStampCopy = computed<boolean>(() => {
    return (
        props.riderStamp?.composition.copySource !== 'none' &&
        ((props.riderStamp?.title.trim() ?? '') !== '' ||
            (props.riderStamp?.description.trim() ?? '') !== '')
    );
});

const showPurposeLine = computed<boolean>(() => {
    const purpose = props.purpose.trim();

    if (purpose === '' || props.riderDesignSource === 'message') {
        return false;
    }

    if (!showStampCopy.value) {
        return true;
    }

    const normalizedPurpose = purpose.toLowerCase();
    const stampCopy = [
        props.riderStamp?.title ?? '',
        props.riderStamp?.description ?? '',
    ].map((value) => value.trim().toLowerCase());

    return !stampCopy.includes(normalizedPurpose);
});

const showClaimQr = computed<boolean>(() => {
    const marker = props.riderStamp?.composition.claimMarker;

    return marker === 'qr' || marker === 'both';
});

const showClaimCode = computed<boolean>(() => {
    const marker = props.riderStamp?.composition.claimMarker;

    return props.riderStamp === null || marker === 'code' || marker === 'both';
});

const claimMarkerPositionClass = computed<string>(() => {
    return {
        top_left: 'top-5 left-6 items-start text-left',
        top_right: 'top-5 right-5 items-end text-right',
        bottom_left: 'bottom-5 left-6 items-start text-left',
        bottom_right: 'right-5 bottom-5 items-end text-right',
    }[props.riderStamp?.composition.claimMarkerPosition ?? 'bottom_right'];
});

const costCurrency = computed<string>(() => {
    return props.costEstimate?.currency?.trim() || props.currency || 'PHP';
});

const costLineItems = computed<
    Array<{
        key: string;
        indicatorKey: string;
        label: string;
        amount: number;
    }>
>(() => {
    const chargeItems = (props.costEstimate?.charges ?? [])
        .map((charge, index) => costChargeLine(charge, index))
        .filter(
            (
                item,
            ): item is {
                key: string;
                indicatorKey: string;
                label: string;
                amount: number;
            } => item !== null,
        );

    if (chargeItems.length > 0) {
        return chargeItems;
    }

    const items: Array<{
        key: string;
        indicatorKey: string;
        label: string;
        amount: number;
    }> = [];
    const baseFee = normalizedCost(props.costEstimate?.base_fee);

    if (baseFee > 0) {
        items.push({
            key: 'base-fee',
            indicatorKey: 'generation',
            label: 'Pay Code Generation',
            amount: baseFee,
        });
    }

    Object.entries(props.costEstimate?.components ?? {}).forEach(
        ([key, value]) => {
            const amount = normalizedCost(value);

            if (amount <= 0 || (key === 'base' && baseFee > 0)) {
                return;
            }

            items.push({
                key,
                indicatorKey: key,
                label: costComponentLabel(key),
                amount,
            });
        },
    );

    return items;
});

const costTotal = computed<number | null>(() => {
    const total = normalizedOptionalCost(props.costEstimate?.total);

    if (total !== null) {
        return total;
    }

    if (props.costEstimate === null) {
        return null;
    }

    return costLineItems.value.reduce((sum, item) => sum + item.amount, 0);
});

const normalizedQuantity = computed<number>(() => {
    const quantity = Number(props.quantity);

    if (!Number.isFinite(quantity) || quantity < 1) {
        return 1;
    }

    return Math.floor(quantity);
});

const extendedCostTotal = computed<number | null>(() => {
    if (costTotal.value === null) {
        return null;
    }

    return (
        Math.round(
            (costTotal.value * normalizedQuantity.value + Number.EPSILON) * 100,
        ) / 100
    );
});

const formattedCostTotal = computed<string>(() => {
    const unitCost = costTotal.value ?? 0;

    if (normalizedQuantity.value === 1) {
        return formattedCost(unitCost);
    }

    return `${normalizedQuantity.value} × ${formattedCost(unitCost)} = ${formattedCost(extendedCostTotal.value ?? 0)}`;
});

const payCodeValue = computed<number | null>(() => {
    return (
        normalizedOptionalCost(props.costEstimate?.pay_code_value) ??
        normalizedOptionalCost(props.amount)
    );
});

const accountDebit = computed<number | null>(() => {
    const authoritativeDebit = normalizedOptionalCost(
        props.costEstimate?.account_debit,
    );

    if (authoritativeDebit !== null) {
        return authoritativeDebit;
    }

    if (payCodeValue.value === null || extendedCostTotal.value === null) {
        return null;
    }

    return (
        Math.round(
            (payCodeValue.value + extendedCostTotal.value + Number.EPSILON) *
                100,
        ) / 100
    );
});

const hasCostEstimate = computed<boolean>(() => {
    return props.costEstimate !== null && costTotal.value !== null;
});

const costLedgerColumnCount = computed<number>(() => {
    return Math.min(3, Math.max(1, Math.ceil(costLineItems.value.length / 7)));
});

const costLedgerColumns = computed<
    Array<
        Array<{
            key: string;
            indicatorKey: string;
            label: string;
            amount: number;
        }>
    >
>(() => {
    if (costLedgerColumnCount.value === 1) {
        return [costLineItems.value];
    }

    const itemsPerColumn = Math.ceil(
        costLineItems.value.length / costLedgerColumnCount.value,
    );

    return Array.from({ length: costLedgerColumnCount.value }, (_, index) =>
        costLineItems.value.slice(
            index * itemsPerColumn,
            (index + 1) * itemsPerColumn,
        ),
    );
});

function costChargeLine(
    charge: PayCodeCostCharge,
    index: number,
): {
    key: string;
    indicatorKey: string;
    label: string;
    amount: number;
} | null {
    const amount = normalizedCost(
        charge.price ?? charge.amount ?? charge.total ?? charge.fee,
    );

    if (amount <= 0) {
        return null;
    }

    const reference =
        stringValue(charge.catalog_item_reference) ??
        stringValue(charge.type) ??
        `charge-${index + 1}`;

    return {
        key: `${reference}-${index}`,
        indicatorKey: reference,
        label: stringValue(charge.label) ?? costComponentLabel(reference),
        amount,
    };
}

function costComponentLabel(key: string): string {
    const labels: Record<string, string> = {
        base: 'Pay Code Generation',
        cash: 'Pay Code Generation',
        generation: 'Pay Code Generation',
        kyc: 'Identity Verification',
        otp: 'One-Time Passcode',
        selfie: 'Selfie Verification',
        signature: 'Signature',
        location: 'Location Verification',
        webhook: 'Webhook Update',
        email_feedback: 'Email Update',
        sms_feedback: 'SMS Update',
        rider: 'Rider',
        validation: 'Validation',
        input_fields: 'Claim Requirements',
    };

    return (
        labels[key] ??
        key
            .replace(/[._-]+/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase())
    );
}

function formattedCost(value: number, includeCurrency = true): string {
    if (!includeCurrency) {
        return value.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    try {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: costCurrency.value,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${costCurrency.value} ${value.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    }
}

function normalizedCost(value: unknown): number {
    return normalizedOptionalCost(value) ?? 0;
}

function normalizedOptionalCost(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const normalized = Number(value);

    return Number.isFinite(normalized) ? normalized : null;
}

function stringValue(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim();

    return normalized === '' ? null : normalized;
}
</script>

<template>
    <section
        class="@container rounded-2xl border border-emerald-200 bg-white/80 p-4 dark:border-emerald-900/70 dark:bg-slate-950/70"
        data-testid="cockpit-pay-code-canvas"
    >
        <div
            class="@md:flex-row @md:items-start @md:justify-between mb-4 flex flex-col gap-3"
            data-testid="cockpit-pay-code-canvas-header"
        >
            <div class="min-w-0">
                <h4
                    class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{
                        presentation === 'finalized'
                            ? 'Issued Pay Code'
                            : 'Pay Code'
                    }}
                </h4>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{
                        presentation === 'finalized'
                            ? 'Final design ready to share.'
                            : 'Preview its Stamp, shape its design, walk through its claim, or inspect cost.'
                    }}
                </p>
            </div>
            <div
                class="@md:flex @md:w-auto grid w-full min-w-0 shrink-0 auto-cols-fr grid-flow-col rounded-full border border-slate-200 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-950"
                aria-label="Pay Code view"
                role="tablist"
                data-testid="cockpit-pay-code-canvas-view-switch"
            >
                <button
                    :id="tabId('stamp')"
                    type="button"
                    role="tab"
                    class="@md:flex-none @md:px-3 min-w-0 rounded-full px-1.5 py-1 text-[0.7rem] font-semibold transition"
                    :class="
                        visibleView === 'stamp'
                            ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                    :aria-selected="visibleView === 'stamp'"
                    :aria-controls="panelId('stamp')"
                    data-testid="cockpit-pay-code-canvas-front-button"
                    @click="visibleView = 'stamp'"
                >
                    Stamp
                </button>
                <button
                    v-if="hasDesignView"
                    :id="tabId('design')"
                    type="button"
                    role="tab"
                    class="@xs:gap-1.5 @md:flex-none @md:px-3 inline-flex min-w-0 items-center justify-center gap-1 rounded-full px-1.5 py-1 text-[0.7rem] font-semibold transition"
                    :class="
                        visibleView === 'design'
                            ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                    :aria-selected="visibleView === 'design'"
                    :aria-controls="panelId('design')"
                    data-testid="cockpit-pay-code-canvas-design-button"
                    @click="visibleView = 'design'"
                >
                    <Palette
                        class="@xs:block hidden size-3"
                        aria-hidden="true"
                    />
                    Design
                </button>
                <button
                    v-if="hasClaimView"
                    :id="tabId('claim')"
                    type="button"
                    role="tab"
                    class="@xs:gap-1.5 @md:flex-none @md:px-3 inline-flex min-w-0 items-center justify-center gap-1 rounded-full px-1.5 py-1 text-[0.7rem] font-semibold transition"
                    :class="
                        visibleView === 'claim'
                            ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                    :aria-selected="visibleView === 'claim'"
                    :aria-controls="panelId('claim')"
                    data-testid="cockpit-pay-code-canvas-claim-button"
                    @click="visibleView = 'claim'"
                >
                    <Route class="@xs:block hidden size-3" aria-hidden="true" />
                    Claim
                </button>
                <button
                    :id="tabId('cost')"
                    type="button"
                    role="tab"
                    class="@md:flex-none @md:px-3 min-w-0 rounded-full px-1.5 py-1 text-[0.7rem] font-semibold transition"
                    :class="
                        visibleView === 'cost'
                            ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                    :aria-selected="visibleView === 'cost'"
                    :aria-controls="panelId('cost')"
                    data-testid="cockpit-pay-code-canvas-back-button"
                    @click="visibleView = 'cost'"
                >
                    Cost
                </button>
            </div>
        </div>

        <article
            v-if="visibleView === 'stamp'"
            :id="panelId('stamp')"
            role="tabpanel"
            :aria-labelledby="tabId('stamp')"
            class="@md:min-h-72 @md:p-7 min-h-88 relative flex aspect-[1.72/1] w-full flex-col overflow-hidden rounded-[1.4rem] border p-5 shadow-xl shadow-slate-900/10"
            :class="
                hasRiderDesign
                    ? 'border-slate-700 bg-slate-950 text-white'
                    : stampDesignClass
            "
            data-testid="cockpit-pay-code-canvas-front"
        >
            <iframe
                v-if="hasRiderDesign && riderDesignDocument !== ''"
                title="Pay Code rider OG design"
                sandbox=""
                tabindex="-1"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 h-full w-full border-0"
                :class="riderArtworkFrameClass"
                data-testid="cockpit-pay-code-canvas-rider-og-design"
                :srcdoc="riderDesignDocument"
            />
            <div
                v-if="hasRiderDesign"
                class="absolute inset-0 bg-gradient-to-r"
                :class="riderArtworkScrimClass"
                aria-hidden="true"
                data-testid="cockpit-pay-code-canvas-rider-scrim"
            />
            <div
                class="absolute inset-y-0 left-0 w-2 bg-emerald-600"
                aria-hidden="true"
            />
            <div
                class="absolute inset-y-0 left-3 w-0.5 bg-emerald-600/50"
                aria-hidden="true"
            />
            <div
                class="absolute -bottom-20 -right-16 size-64 rounded-full border-[2rem] border-emerald-700/5"
                aria-hidden="true"
            />
            <div
                v-if="riderStamp && (showClaimQr || showClaimCode)"
                class="absolute z-20 flex flex-col gap-1.5"
                :class="claimMarkerPositionClass"
                data-testid="cockpit-pay-code-canvas-claim-marker"
            >
                <button
                    v-if="showClaimQr && claimQr && interactiveClaimQr"
                    type="button"
                    class="rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300"
                    :aria-label="`Enlarge claim QR for ${displayedCode}`"
                    data-testid="cockpit-pay-code-canvas-claim-qr-button"
                    @click="emit('claimQrClick')"
                >
                    <img
                        :src="claimQr"
                        :alt="`Claim ${displayedCode}`"
                        class="@md:size-20 size-16 rounded-lg border-4 border-white bg-white object-contain shadow-lg"
                        data-testid="cockpit-pay-code-canvas-claim-qr"
                    />
                </button>
                <img
                    v-else-if="showClaimQr && claimQr"
                    :src="claimQr"
                    :alt="`Claim ${displayedCode}`"
                    class="@md:size-20 size-16 rounded-lg border-4 border-white bg-white object-contain shadow-lg"
                    data-testid="cockpit-pay-code-canvas-claim-qr"
                />
                <span
                    v-else-if="showClaimQr"
                    class="@md:size-16 grid size-14 place-items-center rounded-lg border border-dashed border-current bg-white/85 text-slate-400 shadow-sm dark:bg-slate-950/85"
                    aria-label="Claim QR appears after issue"
                    data-testid="cockpit-pay-code-canvas-claim-qr-placeholder"
                >
                    <QrCode class="size-8" aria-hidden="true" />
                </span>
                <span
                    v-if="showClaimCode"
                    class="rounded-md bg-slate-950/80 px-2 py-1 font-mono text-[0.6rem] font-black tracking-[0.12em] text-white shadow-sm"
                    data-testid="cockpit-pay-code-canvas-claim-code"
                >
                    {{ displayedCode }}
                </span>
            </div>

            <div
                class="relative flex min-h-0 flex-1 flex-col justify-between gap-5"
            >
                <div class="flex items-start justify-between gap-4">
                    <div
                        v-if="showStampLogo"
                        class="flex min-w-0 items-center gap-2.5"
                        data-testid="cockpit-pay-code-canvas-blank-brand"
                    >
                        <img
                            :src="payCodeMarkUrl"
                            alt="Pay Code mark"
                            class="@md:h-14 h-12 w-auto shrink-0 object-contain"
                            data-testid="cockpit-pay-code-canvas-logo"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-[0.65rem] font-black uppercase tracking-[0.22em]"
                                :class="
                                    hasRiderDesign
                                        ? 'text-emerald-300'
                                        : 'text-emerald-700 dark:text-emerald-300'
                                "
                            >
                                Pay Code
                            </p>
                            <p
                                v-if="showStampTagline"
                                class="@md:text-xs mt-1 max-w-52 text-balance text-[0.62rem] font-semibold leading-4"
                                :class="
                                    hasRiderDesign
                                        ? 'text-white/80'
                                        : 'text-slate-600 dark:text-amber-100/70'
                                "
                                data-testid="cockpit-pay-code-canvas-tagline"
                            >
                                The instruction that moves money.
                                <span class="block"
                                    >Generated by X-Change.</span
                                >
                            </p>
                        </div>
                    </div>
                    <div v-else class="min-h-10">
                        <p
                            v-if="showStampTagline"
                            class="@md:text-xs max-w-52 text-balance text-[0.62rem] font-semibold leading-4"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/80'
                                    : 'text-slate-600 dark:text-amber-100/70'
                            "
                            data-testid="cockpit-pay-code-canvas-tagline"
                        >
                            Money should adapt to people.
                            <span class="block">Not the other way around.</span>
                        </p>
                    </div>
                    <div
                        class="flex max-w-[55%] flex-wrap justify-end gap-1.5"
                        aria-label="Pay Code instructions"
                        data-testid="cockpit-pay-code-stamp-indicators"
                    >
                        <CockpitPayCodeIndicator
                            v-for="indicatorKey in visibleStampIndicatorKeys"
                            :key="indicatorKey"
                            :indicator-key="indicatorKey"
                        />
                        <span
                            v-if="hiddenStampIndicators.length > 0"
                            tabindex="0"
                            class="group/more relative inline-grid size-7 place-items-center rounded-full border border-emerald-700/20 bg-emerald-700 text-[0.6rem] font-black text-white shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 dark:border-emerald-300/20 dark:bg-emerald-500 dark:text-slate-950 dark:focus-visible:outline-emerald-300"
                            :aria-label="`${hiddenStampIndicators.length} more instructions`"
                            data-testid="cockpit-pay-code-stamp-indicator-overflow"
                        >
                            +{{ hiddenStampIndicators.length }}
                            <span
                                role="tooltip"
                                class="pointer-events-none absolute right-0 top-full z-50 mt-2 w-max max-w-52 rounded-md bg-slate-950 px-2.5 py-1.5 text-center text-[0.65rem] font-medium leading-4 text-white opacity-0 shadow-xl transition-opacity group-hover/more:opacity-100 group-focus/more:opacity-100"
                            >
                                {{ hiddenStampIndicatorTooltip }}
                            </span>
                        </span>
                    </div>
                </div>

                <div>
                    <div
                        v-if="showStampCopy"
                        class="mb-3 max-w-[82%]"
                        data-testid="cockpit-pay-code-canvas-stamp-copy"
                    >
                        <p
                            class="@md:text-xl line-clamp-1 text-balance text-base font-black"
                        >
                            {{ riderStamp?.title }}
                        </p>
                        <p
                            v-if="riderStamp?.description"
                            class="@md:text-xs mt-1 line-clamp-3 text-[0.65rem] leading-4"
                            data-testid="cockpit-pay-code-canvas-stamp-description"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/75'
                                    : 'text-slate-600 dark:text-amber-100/70'
                            "
                        >
                            {{ riderStamp.description }}
                        </p>
                    </div>
                    <p
                        class="text-[0.65rem] font-semibold uppercase tracking-[0.18em]"
                        :class="
                            hasRiderDesign
                                ? 'text-white/65'
                                : 'text-slate-500 dark:text-amber-100/60'
                        "
                    >
                        Value
                    </p>
                    <p
                        class="@md:text-4xl mt-1 text-3xl font-black tracking-tight"
                        data-testid="cockpit-pay-code-canvas-amount"
                    >
                        {{ formattedAmount }}
                    </p>
                    <p
                        v-if="showPurposeLine"
                        class="mt-1 max-w-[80%] truncate text-xs"
                        :class="
                            hasRiderDesign
                                ? 'text-white/75'
                                : 'text-slate-600 dark:text-amber-100/70'
                        "
                        data-testid="cockpit-pay-code-canvas-purpose"
                    >
                        {{ purpose }}
                    </p>
                </div>

                <div
                    class="@sm:flex-row @sm:items-end @sm:justify-between @sm:gap-4 flex min-w-0 flex-col items-start gap-2"
                    data-testid="cockpit-pay-code-canvas-stamp-footer"
                >
                    <div class="min-w-0">
                        <p
                            class="flex min-w-0 items-center gap-1.5 text-[0.65rem] font-semibold"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/65'
                                    : 'text-slate-500 dark:text-amber-100/60'
                            "
                        >
                            <UserRound
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="shrink-0">Prepared for</span>
                            <span aria-hidden="true">·</span>
                            <span
                                class="truncate text-sm font-bold"
                                :title="recipientLabel"
                                data-testid="cockpit-pay-code-canvas-recipient"
                            >
                                {{ recipientLabel }}
                            </span>
                        </p>
                    </div>
                    <div
                        v-if="riderStamp === null"
                        class="@sm:text-right max-w-full shrink-0 text-left"
                    >
                        <p
                            class="text-[0.6rem] font-semibold uppercase tracking-[0.18em]"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/65'
                                    : 'text-slate-500 dark:text-amber-100/60'
                            "
                        >
                            {{ issuedCode ? 'Issued code' : 'Preview' }}
                        </p>
                        <p
                            class="mt-1 break-words font-mono text-sm font-black tracking-[0.14em]"
                        >
                            {{ displayedCode }}
                        </p>
                    </div>
                </div>
            </div>
        </article>

        <section
            v-show="visibleView === 'design' && hasDesignView"
            :id="panelId('design')"
            role="tabpanel"
            :aria-labelledby="tabId('design')"
            class="h-[clamp(24rem,58vh,36rem)] w-full overflow-hidden rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4 xl:h-[clamp(28rem,64vh,42rem)] 2xl:h-[clamp(28rem,64vh,38rem)] dark:border-slate-800 dark:bg-slate-900/70"
            data-testid="cockpit-pay-code-canvas-design"
        >
            <slot name="design" />
        </section>

        <section
            v-show="visibleView === 'claim' && hasClaimView"
            :id="panelId('claim')"
            role="tabpanel"
            :aria-labelledby="tabId('claim')"
            class="h-[clamp(24rem,58vh,36rem)] w-full overflow-hidden rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-2 xl:h-[clamp(28rem,64vh,42rem)] 2xl:h-[clamp(28rem,64vh,38rem)] dark:border-slate-800 dark:bg-slate-900/70"
            data-testid="cockpit-pay-code-canvas-claim"
        >
            <slot name="claim" />
        </section>

        <article
            v-if="visibleView === 'cost'"
            :id="panelId('cost')"
            role="tabpanel"
            :aria-labelledby="tabId('cost')"
            class="@lg:p-6 relative aspect-[1.72/1] min-h-72 w-full overflow-hidden rounded-[1.4rem] bg-slate-950 p-5 text-white shadow-xl shadow-slate-900/20"
            data-testid="cockpit-pay-code-canvas-back"
        >
            <div class="flex h-full min-w-0 flex-col">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="flex items-center gap-2 text-[0.65rem] font-bold uppercase tracking-[0.2em] text-emerald-300"
                        >
                            <ReceiptText class="size-3.5" aria-hidden="true" />
                            {{
                                presentation === 'finalized'
                                    ? 'Issue Cost'
                                    : 'Estimated Issue Cost'
                            }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <span
                            v-if="costLoading && hasCostEstimate"
                            class="rounded-full bg-white/10 px-2.5 py-1 text-[0.65rem] font-semibold text-slate-300"
                            data-testid="cockpit-pay-code-cost-updating"
                        >
                            Updating…
                        </span>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[0.65rem] font-semibold text-slate-200"
                        >
                            <ArrowLeftRight class="size-3" aria-hidden="true" />
                            {{ expiry }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 flex min-h-0 flex-1 flex-col">
                    <p
                        v-if="costLoading && !hasCostEstimate"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-4 text-sm text-slate-300"
                        data-testid="cockpit-pay-code-cost-loading"
                    >
                        Calculating…
                    </p>

                    <p
                        v-else-if="!hasCostEstimate"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-4 text-sm text-slate-300"
                        data-testid="cockpit-pay-code-cost-unavailable"
                    >
                        {{
                            costError
                                ? 'Estimate unavailable'
                                : 'Enter an amount to see the issue cost.'
                        }}
                    </p>

                    <div
                        v-else
                        class="grid gap-y-3"
                        :class="
                            costLedgerColumnCount === 3
                                ? 'grid-cols-[minmax(0,5fr)_minmax(0,5fr)_minmax(0,7fr)] gap-x-1.5'
                                : costLedgerColumnCount === 2
                                  ? 'grid-cols-2 gap-x-3'
                                  : 'grid-cols-1'
                        "
                        data-testid="cockpit-pay-code-cost-ledger"
                        :data-column-count="costLedgerColumnCount"
                    >
                        <dl
                            v-for="(column, columnIndex) in costLedgerColumns"
                            :key="`cost-column-${columnIndex}`"
                            class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] content-start gap-y-1"
                            :class="[
                                costLedgerColumnCount === 3
                                    ? 'leading-3.5 gap-x-1 text-[0.625rem]'
                                    : costLedgerColumnCount === 2
                                      ? 'gap-x-1.5 text-[0.6875rem] leading-4'
                                      : '@sm:text-sm gap-x-4 text-xs',
                                costLedgerColumnCount > 1 && columnIndex > 0
                                    ? costLedgerColumnCount === 3
                                        ? 'border-l border-white/15 pl-1.5'
                                        : 'border-l border-white/15 pl-2'
                                    : '',
                            ]"
                            data-testid="cockpit-pay-code-cost-ledger-column"
                            :data-column="columnIndex + 1"
                        >
                            <template
                                v-for="(item, itemIndex) in column"
                                :key="item.key"
                            >
                                <dt
                                    class="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap text-slate-300"
                                    :class="
                                        costLedgerColumnCount === 1
                                            ? '@sm:text-sm text-xs'
                                            : ''
                                    "
                                    :title="item.label"
                                    data-testid="cockpit-pay-code-cost-label"
                                >
                                    <CockpitPayCodeIndicator
                                        :indicator-key="item.indicatorKey"
                                        :tooltip="`${item.label} — priced instruction.`"
                                        tone="dark"
                                        size="sm"
                                    />
                                    <span
                                        class="min-w-0 truncate"
                                        data-testid="cockpit-pay-code-cost-label-text"
                                    >
                                        {{ item.label }}
                                    </span>
                                </dt>
                                <dd
                                    class="whitespace-nowrap text-right font-medium tabular-nums text-white"
                                    data-testid="cockpit-pay-code-cost-amount"
                                >
                                    {{
                                        formattedCost(
                                            item.amount,
                                            columnIndex === 0 &&
                                                itemIndex === 0,
                                        )
                                    }}
                                </dd>
                            </template>

                            <dt
                                v-if="
                                    costLineItems.length === 0 &&
                                    columnIndex === 0
                                "
                                class="col-span-2 text-slate-300"
                            >
                                No priced instructions.
                            </dt>
                        </dl>
                    </div>

                    <dl
                        v-if="hasCostEstimate"
                        class="@sm:text-xs mt-auto grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-0.5 border-t border-white/20 pt-2 text-[0.6875rem] leading-4"
                        data-testid="cockpit-pay-code-cost-summary"
                    >
                        <dt class="text-slate-300">Instruction Subtotal</dt>
                        <dd
                            class="whitespace-nowrap text-right font-semibold tabular-nums text-white"
                            :class="
                                normalizedQuantity > 1
                                    ? '@sm:text-[0.6875rem] text-[0.625rem]'
                                    : ''
                            "
                            data-testid="cockpit-pay-code-cost-subtotal"
                            :data-quantity="normalizedQuantity"
                        >
                            {{ formattedCostTotal }}
                        </dd>

                        <dt class="text-slate-300">Pay Code Value</dt>
                        <dd
                            class="whitespace-nowrap text-right font-semibold tabular-nums text-white"
                            data-testid="cockpit-pay-code-cost-pay-code-value"
                        >
                            {{
                                payCodeValue === null
                                    ? '—'
                                    : formattedCost(payCodeValue)
                            }}
                        </dd>

                        <div
                            class="col-span-2 my-1 border-t border-dashed border-white/25"
                            aria-hidden="true"
                        />
                        <dt class="font-bold text-white">
                            Total Estimated Cost
                        </dt>
                        <dd
                            class="@sm:text-base whitespace-nowrap text-right text-sm font-black tabular-nums text-emerald-300"
                            data-testid="cockpit-pay-code-cost-total"
                        >
                            {{
                                accountDebit === null
                                    ? '—'
                                    : formattedCost(accountDebit)
                            }}
                        </dd>
                    </dl>
                </div>
            </div>
        </article>

        <footer
            v-if="$slots.navigation || $slots.action"
            class="mt-3 flex flex-wrap items-center gap-2 px-1"
            data-testid="cockpit-pay-code-canvas-controls"
        >
            <div v-if="$slots.navigation" class="mr-auto flex items-center">
                <slot name="navigation" />
            </div>
            <div
                class="ml-auto flex min-w-0 flex-1 flex-nowrap items-center justify-end gap-2"
                data-testid="cockpit-pay-code-canvas-action-rail"
            >
                <slot name="action" />
            </div>
        </footer>
    </section>
</template>
