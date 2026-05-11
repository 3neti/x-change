<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

interface Props {
    form?: Record<string, any>;
    estimate?: {
        amount?: number;
        quantity?: number;
        subtotal?: number;
        fees?: number;
        total?: number;
        currency?: string;
    } | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    form: () => ({}),
    estimate: null,
    loading: false,
});

const currency = computed(() => props.estimate?.currency ?? 'PHP');

const amount = computed(() => {
    if (props.estimate?.amount !== undefined) {
        return Number(props.estimate.amount);
    }

    return Number(props.form?.amount ?? 0);
});

const quantity = computed(() => {
    if (props.estimate?.quantity !== undefined) {
        return Number(props.estimate.quantity);
    }

    return Number(props.form?.quantity ?? 1);
});

const subtotal = computed(() => {
    if (props.estimate?.subtotal !== undefined) {
        return Number(props.estimate.subtotal);
    }

    return amount.value * quantity.value;
});

const fees = computed(() => Number(props.estimate?.fees ?? 0));
const total = computed(() => {
    if (props.estimate?.total !== undefined) {
        return Number(props.estimate.total);
    }

    return subtotal.value + fees.value;
});

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

                    <div class="flex items-center justify-between text-sm">
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
                    Final fees may vary depending on instruction items and provider configuration.
                </p>
            </template>
        </CardContent>
    </Card>
</template>
