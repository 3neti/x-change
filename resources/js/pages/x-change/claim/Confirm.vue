<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2, ReceiptText } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import PayoutRouteDisplay from '@/components/x-change/PayoutRouteDisplay.vue';

defineOptions({ layout: null });

const routes = useXChangeRoutes();

interface Props {
    claim: {
        voucher_code: string;
        amount: number;
        currency: string;
        formatted_amount: string;
        reference_id: string;
        flow_id: string;
        collected_summary: Record<string, string>;
        wallet?: {
            bank_code?: string | null;
            account_number?: string | null;
            settlement_rail?: string | null;
        };
    };
}

const props = defineProps<Props>();

const form = useForm({
    reference_id: props.claim.reference_id,
    flow_id: props.claim.flow_id,
});

const handleSubmit = () => {
    form.post(routes.claim.submit(props.claim.voucher_code));
};
</script>

<template>
    <Head title="Confirm Claim" />

    <ClaimStepShell>
        <div class="space-y-6">
            <Card class="border-primary/10 shadow-none">
                <CardHeader class="text-center">
                    <ReceiptText class="mx-auto h-10 w-10 text-primary" />
                    <CardTitle class="mt-4">Confirm Claim</CardTitle>
                    <CardDescription>
                        Review and confirm your Pay Code claim
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Amount -->
                    <div class="rounded-lg bg-muted p-4 text-center">
                        <p class="text-sm text-muted-foreground">Amount</p>
                        <p class="text-3xl font-bold">{{ claim.formatted_amount }}</p>
                        <Badge variant="outline" class="mt-1">{{ claim.voucher_code }}</Badge>
                    </div>

                    <PayoutRouteDisplay
                        v-if="claim.wallet?.bank_code || claim.wallet?.account_number"
                        :amount="claim.formatted_amount"
                        :bank-code="claim.wallet?.bank_code"
                        :account-number="claim.wallet?.account_number"
                        :settlement-rail="claim.wallet?.settlement_rail || 'INSTAPAY'"
                    />

                    <!-- Collected data summary -->
                    <div v-if="Object.keys(claim.collected_summary).length > 0" class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Your Details</p>
                        <dl class="space-y-1">
                            <div
                                v-for="(value, label) in claim.collected_summary"
                                :key="label"
                                class="flex justify-between text-sm"
                            >
                                <dt class="text-muted-foreground capitalize">{{ label }}</dt>
                                <dd class="font-medium">{{ value }}</dd>
                            </div>
                        </dl>
                    </div>
                </CardContent>
                <CardFooter class="flex-col gap-3">
                    <Button
                        class="w-full"
                        size="lg"
                        :disabled="form.processing"
                        @click="handleSubmit"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ form.processing ? 'Processing...' : 'Confirm & Claim' }}
                    </Button>
                    <Button
                        variant="ghost"
                        class="w-full"
                        size="sm"
                        :disabled="form.processing"
                        @click="$inertia.visit('/x/claim')"
                    >
                        Cancel
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </ClaimStepShell>
</template>
