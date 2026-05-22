<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle2 } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import RiderRenderer from '@/components/x-rider/RiderRenderer.vue';
import RiderCountdown from '@/components/x-rider/RiderCountdown.vue';
import RiderStageRenderer from '@/components/x-rider/RiderStageRenderer.vue';

defineOptions({ layout: null });

const routes = useXChangeRoutes();

interface VoucherProps {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    formattedAmount?: string | null;
    currency?: string | null;
}

interface RiderContent {
    enabled: boolean;
    type: string;
    content?: string | null;
    meta?: Record<string, unknown>;
}

interface RiderRedirect {
    enabled: boolean;
    url?: string | null;
    timeout: number;
    fallbackUrl?: string | null;
    meta?: Record<string, unknown>;
}

interface RiderStage {
    type: string;
    enabled: boolean;
    key?: string | null;
    payload?: Record<string, unknown>;
    meta?: Record<string, unknown>;
}

interface RiderStageCollection {
    stages?: RiderStage[];
    meta?: Record<string, unknown>;
}

interface RiderExperience {
    state: string;
    success?: RiderContent | null;
    redirect?: RiderRedirect | null;
    stages?: RiderStageCollection | null;
    ads?: unknown[];
    analytics?: Record<string, unknown>;
    meta?: Record<string, unknown>;
}

interface Props {
    voucher: VoucherProps;
    claimOutcome?: string;
    rider?: RiderExperience | null;
    redirectEndpoint?: string | null;
}

const props = defineProps<Props>();

const riderContent = computed(() => props.rider?.success ?? null);
const riderRedirect = computed(() => props.rider?.redirect ?? null);

const riderStages = computed(() =>
    props.rider?.stages?.stages ?? []
);

const hasRenderableStages = computed(() =>
    riderStages.value.some((stage) =>
        stage.enabled && ['message', 'splash', 'link'].includes(stage.type)
    )
);

const hasRiderMessage = computed(() =>
    Boolean(riderContent.value?.enabled && riderContent.value?.content)
);

const hasAnyRiderContent = computed(() =>
    hasRenderableStages.value || hasRiderMessage.value
);

const hasRedirect = computed(() =>
    Boolean(riderRedirect.value?.enabled && props.redirectEndpoint)
);

const numericAmount = computed(() => Number(props.voucher.amount ?? 0));

const hasNonZeroAmount = computed(() => numericAmount.value > 0);

const formattedAmount = computed(() =>
    props.voucher.formatted_amount
    ?? props.voucher.formattedAmount
    ?? (hasNonZeroAmount.value
        ? `${props.voucher.currency ?? ''} ${numericAmount.value.toLocaleString()}`
        : '')
);

const isPending = computed(() =>
    props.claimOutcome === 'accepted_pending'
    || props.rider?.state === 'accepted_pending'
);

const fallbackTitle = computed(() => {
    if (isPending.value) {
        return 'Your claim is being processed';
    }

    return hasNonZeroAmount.value ? 'Disbursed to your account' : 'Pay Code claimed';
});
</script>

<template>
    <Head title="Claim Successful" />

    <div class="min-h-screen bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8">
        <Card class="mx-auto max-w-md border-0 bg-card/80 shadow-sm">
            <CardContent class="space-y-8 px-6 py-8">
                <div class="space-y-4 pt-4 text-center">
                    <CheckCircle2
                        class="mx-auto h-16 w-16"
                        :class="isPending ? 'text-amber-500' : 'text-green-500'"
                    />

                    <RiderStageRenderer
                        v-if="hasRenderableStages"
                        :stages="riderStages"
                    />

                    <RiderRenderer
                        v-else-if="hasRiderMessage"
                        :content="riderContent"
                    />

                    <template v-else>
                        <p v-if="hasNonZeroAmount" class="text-2xl font-bold tracking-tight text-foreground">
                            {{ formattedAmount }}
                        </p>

                        <p class="text-center text-lg font-medium text-foreground">
                            {{ fallbackTitle }}
                        </p>
                    </template>

                    <div
                        v-if="!hasAnyRiderContent"
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 font-mono text-sm font-semibold tracking-widest text-primary"
                    >
                        {{ voucher.code }}
                    </div>
                </div>

                <RiderCountdown
                    v-if="hasRedirect"
                    :redirect="riderRedirect"
                    :redirect-endpoint="redirectEndpoint"
                />

                <div v-else class="flex flex-col gap-3">
                    <Button class="w-full rounded-full" @click="router.visit('/x/claim')">
                        Claim Another
                    </Button>

                    <Button
                        variant="ghost"
                        size="lg"
                        class="w-full rounded-full"
                        @click="router.visit(routes.dashboard)"
                    >
                        Go to Dashboard
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
