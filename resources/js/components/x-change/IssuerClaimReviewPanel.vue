<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ClaimExperienceSummary from '@/components/x-change/ClaimExperienceSummary.vue';
import type { ClaimExperienceSummaryProps } from '@/components/x-change/ClaimExperienceSummary.vue';
import ClaimRequirementSummary from '@/components/x-change/ClaimRequirementSummary.vue';
import type { ClaimRequirementSummaryItem } from '@/components/x-change/ClaimRequirementSummary.vue';
import PayCodeOutcomePanel from '@/components/x-change/PayCodeOutcomePanel.vue';
import PayoutRouteDisplay from '@/components/x-change/PayoutRouteDisplay.vue';

export interface ClaimSurfaceOutcomePanelProps {
    status_key: string;
    status_label: string;
    code?: string | null;
    formatted_amount?: string | null;
    redeemed_at?: string | null;
    payout_status?: string | null;
}

export interface ClaimSurfacePayoutRouteProps {
    bank_code?: string | null;
    settlement_rail?: string | null;
    account_number_masked?: string | null;
}

export interface ClaimSurfaceActionLike {
    key: string;
    label: string;
    href?: string | null;
    method?: string;
    variant?: string;
}

/**
 * The primary deliverable of this slice: an issuer opening a claim URL for
 * their own already-claimed Pay Code sees this review console instead of
 * the generic redeemer page (see `IssuerClaimRequirementSummaryContributor`).
 * This is presentation only -- every value here is already a safe, summary
 * projection produced server-side.
 */
defineProps<{
    headline: string;
    description?: string | null;
    outcomePanel?: ClaimSurfaceOutcomePanelProps | null;
    requirementItems?: ClaimRequirementSummaryItem[] | null;
    claimExperience?: ClaimExperienceSummaryProps | null;
    payoutRoute?: ClaimSurfacePayoutRouteProps | null;
    actions?: ClaimSurfaceActionLike[];
}>();

function actionButtonClass(variant?: string): string {
    return variant === 'primary'
        ? 'inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90'
        : 'inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-sm transition hover:bg-muted/50';
}
</script>

<template>
    <Card data-testid="issuer-claim-review-panel">
        <CardHeader class="space-y-1.5">
            <h2 class="text-lg font-semibold text-foreground">
                {{ headline }}
            </h2>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </CardHeader>

        <CardContent class="space-y-4">
            <PayCodeOutcomePanel
                v-if="outcomePanel"
                :status-key="outcomePanel.status_key"
                :status-label="outcomePanel.status_label"
                :code="outcomePanel.code"
                :formatted-amount="outcomePanel.formatted_amount"
                :redeemed-at="outcomePanel.redeemed_at"
                :payout-status="outcomePanel.payout_status"
            />

            <ClaimRequirementSummary
                v-if="requirementItems && requirementItems.length > 0"
                :items="requirementItems"
            />

            <ClaimExperienceSummary
                v-if="claimExperience"
                v-bind="claimExperience"
            />

            <PayoutRouteDisplay
                v-if="payoutRoute"
                :bank-code="payoutRoute.bank_code"
                :account-number="payoutRoute.account_number_masked"
                :settlement-rail="payoutRoute.settlement_rail"
            />

            <div
                v-if="actions && actions.length > 0"
                class="flex flex-wrap gap-2"
                data-testid="issuer-claim-review-panel-actions"
            >
                <a
                    v-for="action in actions"
                    :key="action.key"
                    :href="action.href || undefined"
                    :class="actionButtonClass(action.variant)"
                    :data-testid="`issuer-claim-review-panel-action-${action.key}`"
                >
                    {{ action.label }}
                </a>
            </div>
        </CardContent>
    </Card>
</template>
