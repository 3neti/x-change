<script setup lang="ts">
defineProps<{
    amount?: string | null;
    bankLabel?: string | null;
    account?: string | null;
    rail?: string | null;
}>();
</script>

<template>
    <div class="space-y-4">
        <div
            data-testid="provider-payout-pending-region"
            role="status"
            class="space-y-2 text-center"
        >
            <h1 class="text-2xl font-semibold text-foreground">Claim received</h1>
            <p class="font-semibold text-amber-700 dark:text-amber-300">Transfer processing</p>
            <p class="text-sm text-muted-foreground">The payment provider is processing your transfer.</p>
        </div>
        <dl
            v-if="bankLabel"
            class="space-y-3 rounded-xl border border-border/70 bg-card p-4 text-left text-sm"
            data-testid="claim-transfer-receipt"
        >
            <div v-if="amount" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Amount</dt>
                <dd class="font-semibold tabular-nums">{{ amount }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-muted-foreground">To</dt>
                <dd class="min-w-0 break-all text-right font-semibold">{{ bankLabel }}<span v-if="account"> · {{ account }}</span></dd>
            </div>
            <div v-if="rail" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Via</dt>
                <dd class="font-semibold">{{ rail }}</dd>
            </div>
        </dl>
    </div>
</template>
