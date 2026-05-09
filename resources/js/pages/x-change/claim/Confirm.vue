<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2, CheckCircle2 } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';

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

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6">
        <div class="w-full max-w-md space-y-6">
            <Card>
                <CardHeader class="text-center">
                    <CheckCircle2 class="mx-auto h-12 w-12 text-primary" />
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
    </div>
</template>
