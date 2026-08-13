<script setup lang="ts">
import { computed } from 'vue';
import ClaimExperienceSummary from '@/components/x-change/ClaimExperienceSummary.vue';
import type { ClaimExperienceSummaryProps } from '@/components/x-change/ClaimExperienceSummary.vue';
import IssuerClaimReviewPanel from '@/components/x-change/IssuerClaimReviewPanel.vue';
import PayCodeOutcomePanel from '@/components/x-change/PayCodeOutcomePanel.vue';
import type {
    ClaimSurfaceActionLike,
    ClaimSurfaceOutcomePanelProps,
    ClaimSurfacePayoutRouteProps,
} from '@/components/x-change/IssuerClaimReviewPanel.vue';
import type { ClaimRequirementSummaryItem } from '@/components/x-change/ClaimRequirementSummary.vue';

interface ClaimSurfaceComponentLike {
    type: string;
    props?: Record<string, unknown>;
}

export interface ClaimSurfaceLike {
    visibility: string;
    headline: string;
    description?: string | null;
    state?: { terminal?: boolean } | null;
    components?: ClaimSurfaceComponentLike[] | null;
    actions?: ClaimSurfaceActionLike[] | null;
}

/**
 * This is the only place on the Vue side that decides how to render a
 * resolved claim surface -- it never adds its own visibility/eligibility
 * logic. What a viewer sees was already decided server-side by
 * `DefaultClaimSurfaceResolver` and its contributors; this component only
 * maps `components`/`actions` onto the matching presentational component.
 */
const props = defineProps<{
    surface?: ClaimSurfaceLike | null;
}>();

function componentProps<T>(type: string): T | null {
    const found = (props.surface?.components ?? []).find(
        (component) => component.type === type,
    );

    return (found?.props as T | undefined) ?? null;
}

const outcomePanel = computed(() =>
    componentProps<ClaimSurfaceOutcomePanelProps>('outcome_panel'),
);

const requirementSummary = computed(() => {
    const summary = componentProps<{ items?: ClaimRequirementSummaryItem[] }>(
        'claim_requirement_summary',
    );

    return summary?.items ?? null;
});

const claimExperienceSummary = computed(() =>
    componentProps<ClaimExperienceSummaryProps>('claim_experience_summary'),
);

const payoutRoute = computed(() =>
    componentProps<ClaimSurfacePayoutRouteProps>('payout_route'),
);

const isIssuerConsole = computed(() => props.surface?.visibility === 'issuer_console');

// Non-issuer terminal states (redeemed/expired/cancelled/etc.) get the calm
// outcome panel in place of the old hard error page. Non-terminal
// public-preview surfaces render nothing here -- the claimable/x-ray
// preview experience is still owned by `ClaimWidget`'s own client-side
// preview flow.
const showOutcomeOnly = computed(
    () => !isIssuerConsole.value && Boolean(props.surface?.state?.terminal) && Boolean(outcomePanel.value),
);
</script>

<template>
    <IssuerClaimReviewPanel
        v-if="isIssuerConsole && surface"
        :headline="surface.headline"
        :description="surface.description"
        :outcome-panel="outcomePanel"
        :requirement-items="requirementSummary"
        :claim-experience="claimExperienceSummary"
        :payout-route="payoutRoute"
        :actions="surface.actions ?? []"
    />

    <div v-else-if="showOutcomeOnly && surface" class="space-y-2" data-testid="claim-surface-outcome">
        <div class="space-y-1 text-center">
            <h1 class="text-xl font-medium">{{ surface.headline }}</h1>
            <p v-if="surface.description" class="text-sm text-muted-foreground">
                {{ surface.description }}
            </p>
        </div>

        <PayCodeOutcomePanel
            v-if="outcomePanel"
            :status-key="outcomePanel.status_key"
            :status-label="outcomePanel.status_label"
            :code="outcomePanel.code"
            :formatted-amount="outcomePanel.formatted_amount"
            :redeemed-at="outcomePanel.redeemed_at"
            :payout-status="outcomePanel.payout_status"
        />

        <ClaimExperienceSummary
            v-if="claimExperienceSummary"
            v-bind="claimExperienceSummary"
        />
    </div>
</template>
