<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import BalanceOverviewCards, {
    type BalanceOverview,
} from '@/components/x-change/BalanceOverviewCards.vue';

interface PricingEstimate {
    currency?: string;
    base_fee?: number;
    components?: Record<string, number>;
    total?: number;
    charges?: Array<Record<string, any>>;

    amount?: number;
    quantity?: number;
    subtotal?: number;
    fees?: number;
}

interface Props {
    form?: Record<string, any>;
    estimate?: PricingEstimate | null;
    loading?: boolean;
    error?: string | null;
    balanceOverview?: BalanceOverview | null;
}

const props = withDefaults(defineProps<Props>(), {
    form: () => ({}),
    estimate: null,
    loading: false,
    error: null,
    balanceOverview: null,
});

const currency = computed(() => props.estimate?.currency ?? 'PHP');

const amount = computed(() => Number(props.form?.amount ?? props.estimate?.amount ?? 0));
const quantity = computed(() => Number(props.form?.quantity ?? props.estimate?.quantity ?? 1));

const subtotal = computed(() => {
    if (props.estimate?.subtotal !== undefined) {
        return Number(props.estimate.subtotal);
    }

    return amount.value * quantity.value;
});

const components = computed<Record<string, number>>(() => props.estimate?.components ?? {});
const charges = computed<Array<Record<string, any>>>(() => props.estimate?.charges ?? []);

const legacyFees = computed(() => Number(props.estimate?.fees ?? 0));
const baseFee = computed(() => Number(props.estimate?.base_fee ?? 0));

const visibleComponents = computed(() => {
    return Object.entries(components.value)
        .map(([key, value]) => ({
            key,
            label: componentLabel(key),
            amount: Number(value || 0),
        }))
        .filter((item) => item.amount > 0);
});

const visibleCharges = computed(() => {
    return charges.value
        .map((charge, index) => ({
            key: `${chargeLabel(charge, index)}-${index}`,
            label: chargeLabel(charge, index),
            amount: chargeAmount(charge),
        }))
        .filter((item) => item.amount > 0);
});

const totalFees = computed(() => {
    const itemizedTotal = visibleComponents.value.length
        ? visibleComponents.value.reduce((sum, item) => sum + item.amount, 0)
        : visibleCharges.value.reduce((sum, item) => sum + item.amount, 0);

    return itemizedTotal || legacyFees.value || baseFee.value;
});

const total = computed(() => subtotal.value + totalFees.value);

const hasEstimate = computed(() => props.estimate !== null);
const showInitialLoading = computed(() => props.loading && !hasEstimate.value);
const showInitialError = computed(() => props.error && !hasEstimate.value);

function labelize(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function componentLabel(key: string): string {
    const labels: Record<string, string> = {
        cash: 'Cash-out / Disbursement Fee',
        kyc: 'KYC',
        otp: 'OTP',
        selfie: 'Selfie',
        signature: 'Signature',
        location: 'Location',
        webhook: 'Webhook',
        email_feedback: 'Email Feedback',
        sms_feedback: 'SMS Feedback',
        rider: 'Rider',
        validation: 'Validation',
        input_fields: 'Input Fields',
        base: 'Base Fee',
    };

    return labels[key] ?? labelize(key);
}

function chargeLabel(charge: Record<string, any>, index: number): string {
    return String(
        charge.label ||
        charge.name ||
        charge.code ||
        charge.item ||
        `Charge ${index + 1}`,
    );
}

function chargeAmount(charge: Record<string, any>): number {
    return Number(
        charge.amount ??
        charge.total ??
        charge.price ??
        charge.fee ??
        0,
    );
}

function money(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency.value,
    }).format(Number.isFinite(value) ? value : 0);
}
</script>

<template>
    <Card>
        <CardHeader class="space-y-1">
            <CardTitle class="text-base">Cost Estimate</CardTitle>

            <p class="min-h-4 text-xs text-muted-foreground">
                <template v-if="loading && estimate">
                    Updating estimate…
                </template>
                <template v-else-if="error && estimate">
                    Showing last estimate. Refresh failed.
                </template>
                <template v-else-if="estimate">
                    Based on active x-change pricing rules.
                </template>
                <template v-else>
                    &nbsp;
                </template>
            </p>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="showInitialLoading" class="rounded-lg border border-dashed p-6 text-center">
                <p class="text-sm text-muted-foreground">Estimating…</p>
            </div>

            <div v-else-if="showInitialError" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                <p class="text-sm font-medium text-destructive">
                    Unable to estimate cost
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ error }}
                </p>
            </div>

            <div v-else class="space-y-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Cash to Disburse
                </p>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Amount</span>
                    <span class="font-medium">{{ money(amount) }}</span>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Quantity</span>
                    <span class="font-medium">{{ quantity || 1 }}</span>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Cash Subtotal</span>
                    <span class="font-medium">{{ money(subtotal) }}</span>
                </div>

                <Separator />

                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Service & Instruction Fees
                </p>

                <template v-if="visibleComponents.length">
                    <div
                        v-for="item in visibleComponents"
                        :key="item.key"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-muted-foreground">{{ item.label }}</span>
                        <span class="font-medium">{{ money(item.amount) }}</span>
                    </div>
                </template>

                <template v-else-if="visibleCharges.length">
                    <div
                        v-for="item in visibleCharges"
                        :key="item.key"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-muted-foreground">{{ item.label }}</span>
                        <span class="font-medium">{{ money(item.amount) }}</span>
                    </div>
                </template>

                <div v-else class="text-sm text-muted-foreground">
                    No additional fees.
                </div>

                <Separator />

                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">Total Fees</span>
                    <span class="font-medium">{{ money(totalFees) }}</span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="font-medium">Required Funding</span>
                    <span class="text-xl font-bold">{{ money(total) }}</span>
                </div>

                <p class="min-h-4 text-xs text-muted-foreground">
                    Required funding includes the cash amount plus service and instruction fees.
                </p>

                <BalanceOverviewCards
                    v-if="balanceOverview"
                    :overview="balanceOverview"
                    :required-amount="total"
                    compact
                />
            </div>
        </CardContent>
    </Card>
</template>
