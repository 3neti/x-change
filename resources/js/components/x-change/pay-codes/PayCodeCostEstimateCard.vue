<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

interface PricingEstimate {
    currency?: string;
    base_fee?: number;
    components?: Record<string, number>;
    total?: number;
    charges?: Array<Record<string, any>>;

    // Backward/local fallback shape
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
}

const props = withDefaults(defineProps<Props>(), {
    form: () => ({}),
    estimate: null,
    loading: false,
    error: null,
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

const components = computed(() => props.estimate?.components ?? {});
const charges = computed(() => props.estimate?.charges ?? []);

const componentTotal = computed(() => {
    return Object.values(components.value).reduce((sum, value) => {
        return sum + Number(value || 0);
    }, 0);
});

const legacyFees = computed(() => Number(props.estimate?.fees ?? 0));
const baseFee = computed(() => Number(props.estimate?.base_fee ?? 0));

const fees = computed(() => {
    return componentTotal.value || legacyFees.value || baseFee.value;
});

const total = computed(() => {
    if (props.estimate?.total !== undefined) {
        return Number(props.estimate.total);
    }

    return subtotal.value + fees.value;
});

const hasServerEstimate = computed(() => props.estimate !== null);

function labelize(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
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
        <CardHeader>
            <CardTitle class="text-base">Cost Estimate</CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="loading" class="rounded-lg border border-dashed p-6 text-center">
                <p class="text-sm text-muted-foreground">Estimating…</p>
            </div>

            <div v-else-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                <p class="text-sm font-medium text-destructive">
                    Unable to estimate cost
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ error }}
                </p>
            </div>

            <template v-else>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Amount</span>
                        <span class="font-medium">{{ money(amount) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Quantity</span>
                        <span class="font-medium">{{ quantity || 1 }}</span>
                    </div>

                    <Separator />

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-medium">{{ money(subtotal) }}</span>
                    </div>

                    <template v-if="Object.keys(components).length">
                        <div
                            v-for="(value, key) in components"
                            :key="key"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{ labelize(String(key)) }}</span>
                            <span class="font-medium">{{ money(Number(value || 0)) }}</span>
                        </div>
                    </template>

                    <template v-else-if="charges.length">
                        <div
                            v-for="(charge, index) in charges"
                            :key="`${chargeLabel(charge, index)}-${index}`"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{ chargeLabel(charge, index) }}</span>
                            <span class="font-medium">{{ money(chargeAmount(charge)) }}</span>
                        </div>
                    </template>

                    <div v-else class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Fees</span>
                        <span class="font-medium">{{ money(fees) }}</span>
                    </div>

                    <Separator />

                    <div class="flex items-center justify-between">
                        <span class="font-medium">Estimated Total</span>
                        <span class="text-xl font-bold">{{ money(total) }}</span>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    <template v-if="hasServerEstimate">
                        Estimate is based on the active x-change pricing rules.
                    </template>
                    <template v-else>
                        Enter an amount to estimate pricing.
                    </template>
                </p>
            </template>
        </CardContent>
    </Card>
</template>
