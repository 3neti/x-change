<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { statusBadgeVariant, statusIcon } from '@/components/x-change/claimSurfaceViewModel';

/**
 * Calm replacement for the old tilted `VoucherStatusStamp`, driven entirely
 * by the `outcome_panel` component contributed server-side (see
 * `NonActiveOutcomeContributor`). Presentation only -- the status key/label
 * come straight from the backend, this only decides icon/badge tone and
 * layout.
 */
const props = defineProps<{
    statusKey: string;
    statusLabel: string;
    code?: string | null;
    formattedAmount?: string | null;
    redeemedAt?: string | null;
    payoutStatus?: string | null;
}>();

const icon = computed(() => statusIcon(props.statusKey));
const badgeVariant = computed(() => statusBadgeVariant(props.statusKey));

const badgeLabel = computed(() => {
    const labels: Record<string, string> = {
        redeemed: 'Complete',
        paid: 'Complete',
        partially_claimed: 'Still claimable',
        payout_pending: 'Pending',
        awaiting_approval: 'Needs approval',
        expired: 'Expired',
        cancelled: 'Cancelled',
        closed: 'Closed',
        payout_rejected: 'Failed',
    };

    const label = labels[props.statusKey] ?? null;

    return label !== props.statusLabel ? label : null;
});

function formatDate(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const formattedRedeemedAt = computed(() => formatDate(props.redeemedAt));
</script>

<template>
    <Card data-testid="pay-code-outcome-panel">
        <CardContent class="space-y-3 py-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <component
                        :is="icon"
                        class="h-5 w-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="truncate text-base font-semibold text-foreground">
                        {{ statusLabel }}
                    </p>
                </div>
                <Badge v-if="badgeLabel" :variant="badgeVariant" class="shrink-0">
                    {{ badgeLabel }}
                </Badge>
            </div>

            <p v-if="formattedAmount" class="text-2xl font-bold tracking-tight text-foreground">
                {{ formattedAmount }}
            </p>

            <div v-if="code" class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-3 py-0.5 font-mono text-xs font-semibold tracking-widest text-primary">
                {{ code }}
            </div>

            <p v-if="formattedRedeemedAt" class="text-xs text-muted-foreground" data-testid="pay-code-outcome-redeemed-at">
                Redeemed on {{ formattedRedeemedAt }}
            </p>

            <p v-if="payoutStatus" class="text-xs text-muted-foreground" data-testid="pay-code-outcome-payout-status">
                Payout status: {{ payoutStatus }}
            </p>
        </CardContent>
    </Card>
</template>
