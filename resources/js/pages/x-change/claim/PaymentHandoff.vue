<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { CheckCircle2, CreditCard } from 'lucide-vue-next';

defineOptions({ layout: null });

defineProps<{
    code: string;
    payment_url?: string | null;
    is_fully_collected: boolean;
}>();
</script>

<template>
    <Head
        :title="
            is_fully_collected
                ? 'Pay Code fully paid'
                : 'Pay Code payment required'
        "
    />

    <ClaimStepShell :tone="is_fully_collected ? 'success' : 'neutral'" width="sm">
        <div class="space-y-5 text-center" data-testid="payment-handoff-page">
            <component
                :is="is_fully_collected ? CheckCircle2 : CreditCard"
                class="mx-auto h-11 w-11"
                :class="
                    is_fully_collected
                        ? 'text-emerald-600'
                        : 'text-primary'
                "
                aria-hidden="true"
            />

            <div class="space-y-2">
                <p
                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                >
                    Pay Code
                </p>
                <p class="font-mono text-2xl font-semibold tracking-[0.12em]">
                    {{ code }}
                </p>
            </div>

            <div class="space-y-2">
                <h1
                    class="text-xl font-semibold tracking-tight"
                    data-testid="payment-handoff-title"
                >
                    {{
                        is_fully_collected
                            ? 'This Pay Code has already been fully paid'
                            : 'This Pay Code is for payment'
                    }}
                </h1>

                <p
                    class="mx-auto max-w-xs text-sm leading-6 text-muted-foreground"
                    data-testid="payment-handoff-description"
                >
                    {{
                        is_fully_collected
                            ? 'There is no remaining amount to pay on this Pay Code.'
                            : 'This code receives money from the payer. It cannot be claimed as a payout.'
                    }}
                </p>
            </div>

            <Button
                v-if="payment_url"
                as-child
                class="w-full"
                data-testid="payment-handoff-open-payment"
            >
                <Link :href="payment_url">Open payment page</Link>
            </Button>
        </div>
    </ClaimStepShell>
</template>
