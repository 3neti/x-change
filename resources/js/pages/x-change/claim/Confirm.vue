<script setup lang="ts">
import ClaimReviewPanel from '@/components/x-change/ClaimReviewPanel.vue';
import { Head, useForm } from '@inertiajs/vue3';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
    CardFooter,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2 } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';

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
        <ClaimReviewPanel
            :code="claim.voucher_code"
            :amount="claim.formatted_amount"
            :fields="claim.collected_summary"
            :wallet="claim.wallet"
            :processing="form.processing"
            @confirm="handleSubmit"
            @cancel="$inertia.visit('/x/claim')"
        />
    </ClaimStepShell>
</template>
