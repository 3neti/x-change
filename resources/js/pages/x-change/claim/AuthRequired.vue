<script setup lang="ts">
import ClaimStatePanel from '@/components/x-change/ClaimStatePanel.vue';
import FormFlowActions from '@/pages/form-flow/core/components/FormFlowActions.vue';
import { useClaimActionLabel } from '@/composables/useClaimActionLabel';

import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { ShieldCheck } from 'lucide-vue-next';

const claimActionLabel = useClaimActionLabel();

defineOptions({ layout: null });

type AuthIntent = {
    type: 'campaign_authorization' | 'onboarding_claimant_handoff';
    authentication_mode: 'authenticated_officer' | 'claimant_handoff';
    code: string;
    title: string;
    description: string;
    intended_url: string;
};

type Workflow = {
    key: string;
    title: string;
    description: string;
    review?: Record<string, string | number | boolean | null>;
};

defineProps<{
    code: string;
    login_url: string;
    claim_url: string;
    intent: AuthIntent;
    workflow: Workflow;
}>();
</script>

<template>
    <Head :title="intent.title" />

    <ClaimStepShell tone="warning" width="lg">
        <div class="space-y-5" data-testid="claim-auth-required-page">
            <ClaimStatePanel role="attention" class="space-y-3 text-center">
                <div class="space-y-1">
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-amber-700 uppercase"
                    >
                        Protected claim
                    </p>
                    <h1
                        class="text-2xl font-semibold tracking-tight"
                        data-testid="claim-auth-required-title"
                    >
                        {{ intent.title }}
                    </h1>
                </div>

                <p
                    class="mx-auto max-w-md text-sm leading-6 text-muted-foreground"
                    data-testid="claim-auth-required-description"
                >
                    {{ intent.description }}
                </p>
            </ClaimStatePanel>

            <div
                class="rounded-lg border border-border/70 bg-background/70 px-4 py-3"
                data-testid="claim-auth-required-code"
            >
                <p class="text-xs text-muted-foreground">Pay Code</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ code }}</p>
            </div>

            <div class="space-y-2 text-sm leading-6 text-muted-foreground">
                <p data-testid="claim-auth-required-workflow">
                    {{ workflow.description }}
                </p>
                <p>
                    Authentication does not issue Pay Codes, deliver messages,
                    transfer funds, or complete this claim by itself.
                </p>
            </div>

            <FormFlowActions>
                <Button as-child class="w-full" data-testid="claim-auth-login">
                    <a :href="login_url">{{
                        claimActionLabel('open_link', 'Continue to sign in')
                    }}</a>
                </Button>

                <Button
                    as-child
                    variant="outline"
                    class="w-full"
                    data-testid="claim-auth-return"
                >
                    <a :href="claim_url">{{
                        claimActionLabel('retry', 'Retry claim link')
                    }}</a>
                </Button>
            </FormFlowActions>
        </div>
    </ClaimStepShell>
</template>
