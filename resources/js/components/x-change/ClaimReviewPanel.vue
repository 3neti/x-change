<script setup lang="ts">
import ClaimStatusIcon from './ClaimStatusIcon.vue';
import PayoutRouteDisplay from './PayoutRouteDisplay.vue';
import FormFlowActions from '@/pages/form-flow/core/components/FormFlowActions.vue';
defineProps<{
    code: string;
    amount: string;
    fields: Record<string, string>;
    wallet?: {
        bank_code?: string | null;
        account_number?: string | null;
        settlement_rail?: string | null;
    };
    processing?: boolean;
}>();
defineEmits<{ confirm: []; cancel: [] }>();
</script>
<template>
    <section
        data-testid="claim-review-panel"
        data-flow-stack
        class="grid gap-6"
    >
        <header class="text-center">
            <ClaimStatusIcon role="review" data-testid="claim-review-icon" />
            <h1 class="mt-4 text-xl font-semibold">Confirm Claim</h1>
            <p class="text-sm text-muted-foreground">
                Review and confirm your Pay Code claim
            </p>
        </header>
        <div
            class="rounded-lg bg-muted p-4 text-center"
            style="container-type: inline-size"
        >
            <p class="text-sm text-muted-foreground">Amount</p>
            <p class="text-3xl font-bold">{{ amount }}</p>
            <span
                class="mt-1 block whitespace-nowrap font-mono"
                :style="{
                    fontSize: `clamp(0.625rem, ${125 / (code.length + 8)}cqw, 0.875rem)`,
                }"
            >
                || {{ code }} ||
            </span>
        </div>
        <PayoutRouteDisplay
            v-if="wallet?.bank_code || wallet?.account_number"
            :amount="amount"
            :bank-code="wallet?.bank_code"
            :account-number="wallet?.account_number"
            :settlement-rail="wallet?.settlement_rail || 'INSTAPAY'"
        />
        <div v-if="Object.keys(fields).length" class="space-y-2">
            <p class="text-sm font-medium text-muted-foreground">
                Your Details
            </p>
            <dl class="space-y-1">
                <div
                    v-for="(value, label) in fields"
                    :key="label"
                    class="flex min-w-0 justify-between gap-3 text-sm"
                >
                    <dt class="text-muted-foreground capitalize">
                        {{ label }}
                    </dt>
                    <dd class="min-w-0 break-words text-right font-medium">
                        {{ value }}
                    </dd>
                </div>
            </dl>
        </div>
        <FormFlowActions
            primary-role="confirm_claim"
            secondary-role="cancel"
            primary-label="Confirm &amp; Claim"
            secondary-label="Cancel"
            primary-type="button"
            :processing="processing"
            @primary="$emit('confirm')"
            @secondary="$emit('cancel')"
        />
    </section>
</template>
