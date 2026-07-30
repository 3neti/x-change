<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: null });

type AuthIntent = {
    type: 'campaign_authorization';
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
    <Head title="Officer authorization required" />

    <ClaimStepShell tone="warning" width="lg">
        <div class="space-y-5" data-testid="claim-auth-required-page">
            <div class="space-y-3 text-center">
                <ShieldCheck class="mx-auto h-12 w-12 text-amber-600" />

                <div class="space-y-1">
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-amber-700 uppercase"
                    >
                        Campaign approval
                    </p>
                    <h1
                        class="text-2xl font-semibold tracking-tight"
                        data-testid="claim-auth-required-title"
                    >
                        Officer authorization required
                    </h1>
                </div>

                <p
                    class="mx-auto max-w-md text-sm leading-6 text-muted-foreground"
                    data-testid="claim-auth-required-description"
                >
                    This Pay Code approves a frozen campaign worksheet. Sign in
                    with the officer account authorized to review and approve
                    it.
                </p>
            </div>

            <div
                class="rounded-lg border border-border/70 bg-background/70 px-4 py-3"
                data-testid="claim-auth-required-code"
            >
                <p class="text-xs text-muted-foreground">Approval Pay Code</p>
                <p class="mt-1 font-mono text-lg font-semibold">{{ code }}</p>
            </div>

            <div class="space-y-2 text-sm leading-6 text-muted-foreground">
                <p data-testid="claim-auth-required-workflow">
                    {{ workflow.description }}
                </p>
                <p>
                    Signing in here does not issue Pay Codes, deliver messages,
                    transfer funds, or redeem a beneficiary payout by itself.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <Button as-child class="w-full" data-testid="claim-auth-login">
                    <a :href="login_url">Continue to sign in</a>
                </Button>

                <Button
                    as-child
                    variant="outline"
                    class="w-full"
                    data-testid="claim-auth-return"
                >
                    <a :href="claim_url">Retry claim link</a>
                </Button>
            </div>
        </div>
    </ClaimStepShell>
</template>
