<script setup lang="ts">
import {
    ArrowLeftRight,
    ReceiptText,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type {
    PayCodeCostCharge,
    PayCodeCostEstimate,
} from '../../composables/usePayCodeCostEstimate';

const props = withDefaults(
    defineProps<{
        amount: string | number;
        currency: string;
        recipient?: string;
        purpose?: string;
        claimOutcome: 'provider_disbursement' | 'account_funding';
        voucherType: 'redeemable' | 'payable' | 'settlement';
        expiry?: string;
        instructionLabels?: string[];
        issuedCode?: string | null;
        hasRiderDesign?: boolean;
        riderDesignDocument?: string;
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
        instructionLabels: () => [],
        issuedCode: null,
        hasRiderDesign: false,
        riderDesignDocument: '',
        presentation: 'live',
        costEstimate: null,
        costLoading: false,
        costError: null,
        quantity: 1,
    },
);

const visibleSide = ref<'front' | 'back'>('front');
const xChangeLogoUrl = '/vendor/x-change/images/logo-orange.png';

const formattedAmount = computed<string>(() => {
    const value = Number(props.amount);

    if (!Number.isFinite(value)) {
        return `${props.currency || 'PHP'} 0.00`;
    }

    return `${props.currency || 'PHP'} ${value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
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

const capabilityLabel = computed<string>(() => {
    if (props.claimOutcome === 'account_funding') {
        return 'Add to Account';
    }

    if (props.voucherType === 'payable') {
        return 'Collect Payment';
    }

    if (props.voucherType === 'settlement') {
        return 'Settlement';
    }

    return 'Receive Funds';
});

const displayedCode = computed<string>(() => {
    return props.issuedCode?.trim() || 'PAY CODE PREVIEW';
});

const isBlankCanvas = computed<boolean>(() => {
    return (
        String(props.amount).trim() === '' &&
        props.recipient.trim() === '' &&
        props.purpose.trim() === '' &&
        !props.issuedCode?.trim() &&
        !props.hasRiderDesign
    );
});

const costCurrency = computed<string>(() => {
    return props.costEstimate?.currency?.trim() || props.currency || 'PHP';
});

const costLineItems = computed<
    Array<{ key: string; label: string; amount: number }>
>(() => {
    const chargeItems = (props.costEstimate?.charges ?? [])
        .map((charge, index) => costChargeLine(charge, index))
        .filter(
            (item): item is { key: string; label: string; amount: number } =>
                item !== null,
        );

    if (chargeItems.length > 0) {
        return chargeItems;
    }

    const items: Array<{ key: string; label: string; amount: number }> = [];
    const baseFee = normalizedCost(props.costEstimate?.base_fee);

    if (baseFee > 0) {
        items.push({
            key: 'base-fee',
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

const hasCostEstimate = computed<boolean>(() => {
    return props.costEstimate !== null && costTotal.value !== null;
});

const costLedgerColumnCount = computed<number>(() => {
    return Math.min(3, Math.max(1, Math.ceil(costLineItems.value.length / 7)));
});

const costLedgerColumns = computed<
    Array<Array<{ key: string; label: string; amount: number }>>
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
): { key: string; label: string; amount: number } | null {
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
        rider: 'Claim Experience',
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
        class="@container rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80"
        data-testid="cockpit-pay-code-canvas"
    >
        <div
            class="mb-3 flex flex-wrap items-center justify-between gap-3 px-1"
        >
            <div>
                <p
                    class="text-[0.65rem] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                >
                    {{
                        presentation === 'finalized'
                            ? 'Issued Pay Code'
                            : 'Live Pay Code'
                    }}
                </p>
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{
                        presentation === 'finalized'
                            ? 'Final design ready to share.'
                            : 'Updates as you design.'
                    }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <slot name="action" />
                <div
                    class="inline-flex rounded-full border border-slate-200 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-950"
                    aria-label="Pay Code side"
                >
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-[0.7rem] font-semibold transition"
                        :class="
                            visibleSide === 'front'
                                ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                        data-testid="cockpit-pay-code-canvas-front-button"
                        @click="visibleSide = 'front'"
                    >
                        Front
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-[0.7rem] font-semibold transition"
                        :class="
                            visibleSide === 'back'
                                ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                        data-testid="cockpit-pay-code-canvas-back-button"
                        @click="visibleSide = 'back'"
                    >
                        Back
                    </button>
                </div>
            </div>
        </div>

        <article
            v-if="visibleSide === 'front'"
            class="relative aspect-[1.72/1] min-h-72 w-full overflow-hidden rounded-[1.4rem] border p-5 shadow-xl shadow-slate-900/10 @md:p-7"
            :class="
                hasRiderDesign
                    ? 'border-slate-700 bg-slate-950 text-white'
                    : 'border-amber-200 bg-[#fffaf0] text-slate-950 dark:border-amber-900/60 dark:bg-[#19170f] dark:text-amber-50'
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
                data-testid="cockpit-pay-code-canvas-rider-og-design"
                :srcdoc="riderDesignDocument"
            />
            <div
                v-if="hasRiderDesign"
                class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/45 to-black/10"
                aria-hidden="true"
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
                class="absolute -right-16 -bottom-20 size-64 rounded-full border-[2rem] border-emerald-700/5"
                aria-hidden="true"
            />

            <div class="relative flex h-full flex-col justify-between gap-5">
                <div class="flex items-start justify-between gap-4">
                    <div
                        v-if="isBlankCanvas"
                        class="flex min-w-0 items-center gap-2.5"
                        data-testid="cockpit-pay-code-canvas-blank-brand"
                    >
                        <img
                            :src="xChangeLogoUrl"
                            alt="x-change logo"
                            class="h-12 w-auto shrink-0 object-contain @md:h-14"
                            data-testid="cockpit-pay-code-canvas-logo"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-[0.65rem] font-black tracking-[0.22em] text-emerald-700 uppercase dark:text-emerald-300"
                            >
                                x-change
                            </p>
                            <p
                                class="mt-1 max-w-52 text-[0.62rem] leading-4 font-semibold text-balance text-slate-600 @md:text-xs dark:text-amber-100/70"
                                data-testid="cockpit-pay-code-canvas-tagline"
                            >
                                Money should adapt to people.
                                <span class="block"
                                    >Not the other way around.</span
                                >
                            </p>
                        </div>
                    </div>
                    <div v-else>
                        <p
                            class="text-[0.65rem] font-black tracking-[0.22em] uppercase"
                            :class="
                                hasRiderDesign
                                    ? 'text-emerald-300'
                                    : 'text-emerald-700 dark:text-emerald-300'
                            "
                        >
                            x-change
                        </p>
                        <p
                            class="mt-1 text-xs font-semibold"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/65'
                                    : 'text-slate-500 dark:text-amber-100/60'
                            "
                        >
                            Digital Pay Code
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-emerald-700 px-3 py-1 text-[0.65rem] font-bold tracking-wide text-white uppercase"
                    >
                        {{ capabilityLabel }}
                    </span>
                </div>

                <div>
                    <p
                        class="text-[0.65rem] font-semibold tracking-[0.18em] uppercase"
                        :class="
                            hasRiderDesign
                                ? 'text-white/65'
                                : 'text-slate-500 dark:text-amber-100/60'
                        "
                    >
                        Value
                    </p>
                    <p
                        class="mt-1 text-3xl font-black tracking-tight @md:text-4xl"
                        data-testid="cockpit-pay-code-canvas-amount"
                    >
                        {{ formattedAmount }}
                    </p>
                    <p
                        v-if="purpose"
                        class="mt-1 max-w-[80%] truncate text-xs"
                        :class="
                            hasRiderDesign
                                ? 'text-white/75'
                                : 'text-slate-600 dark:text-amber-100/70'
                        "
                    >
                        {{ purpose }}
                    </p>
                </div>

                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="flex items-center gap-1.5 text-[0.65rem] font-semibold"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/65'
                                    : 'text-slate-500 dark:text-amber-100/60'
                            "
                        >
                            <UserRound class="size-3.5" aria-hidden="true" />
                            Prepared for
                        </p>
                        <p
                            class="mt-1 truncate text-sm font-bold"
                            data-testid="cockpit-pay-code-canvas-recipient"
                        >
                            {{ recipientLabel }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p
                            class="text-[0.6rem] font-semibold tracking-[0.18em] uppercase"
                            :class="
                                hasRiderDesign
                                    ? 'text-white/65'
                                    : 'text-slate-500 dark:text-amber-100/60'
                            "
                        >
                            {{ issuedCode ? 'Issued code' : 'Preview' }}
                        </p>
                        <p
                            class="mt-1 font-mono text-sm font-black tracking-[0.14em]"
                        >
                            {{ displayedCode }}
                        </p>
                    </div>
                </div>
            </div>
        </article>

        <article
            v-else
            class="relative aspect-[1.72/1] min-h-72 w-full overflow-hidden rounded-[1.4rem] bg-slate-950 p-5 text-white shadow-xl shadow-slate-900/20 @lg:p-6"
            data-testid="cockpit-pay-code-canvas-back"
        >
            <div class="flex h-full min-w-0 flex-col">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p
                            class="flex items-center gap-2 text-[0.65rem] font-bold tracking-[0.2em] text-emerald-300 uppercase"
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

                <div class="mt-3 min-h-0 flex-1">
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
                                ? 'grid-cols-3 gap-x-1.5'
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
                                    ? 'gap-x-1 text-[0.625rem] leading-3.5'
                                    : costLedgerColumnCount === 2
                                      ? 'gap-x-1.5 text-[0.6875rem] leading-4'
                                      : 'gap-x-4 text-xs @sm:text-sm',
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
                                    class="min-w-0 break-words text-slate-300"
                                    :class="
                                        costLedgerColumnCount > 1
                                            ? 'line-clamp-2 text-pretty'
                                            : 'whitespace-normal'
                                    "
                                    :title="item.label"
                                    data-testid="cockpit-pay-code-cost-label"
                                >
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="text-right font-medium whitespace-nowrap text-white tabular-nums"
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

                            <template
                                v-if="
                                    costLedgerColumnCount === 1 ||
                                    columnIndex === costLedgerColumns.length - 1
                                "
                            >
                                <div
                                    class="col-span-2 my-1 border-t border-dashed border-white/25"
                                    aria-hidden="true"
                                />
                                <dt class="font-bold text-white">Total</dt>
                                <dd
                                    class="text-right font-black whitespace-nowrap text-emerald-300 tabular-nums"
                                    :class="
                                        normalizedQuantity > 1
                                            ? 'text-[0.625rem] leading-4 @sm:text-xs'
                                            : 'text-base @sm:text-lg'
                                    "
                                    data-testid="cockpit-pay-code-cost-total"
                                    :data-quantity="normalizedQuantity"
                                >
                                    {{ formattedCostTotal }}
                                </dd>
                            </template>
                        </dl>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span
                        v-for="label in instructionLabels.slice(0, 3)"
                        :key="label"
                        class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[0.65rem] font-semibold text-slate-200"
                    >
                        <ShieldCheck class="size-3" aria-hidden="true" />
                        {{ label }}
                    </span>
                </div>
            </div>
        </article>
    </section>
</template>
