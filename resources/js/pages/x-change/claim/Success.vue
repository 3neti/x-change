<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ExternalLink, CheckCircle2 } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import { marked } from 'marked';

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

interface RiderExperience {
    state: string;
    success?: RiderContent | null;
    redirect?: RiderRedirect | null;
    ads?: unknown[];
    analytics?: Record<string, unknown>;
    meta?: Record<string, unknown>;
}

interface Props {
    voucher: VoucherProps;
    claimOutcome?: string;
    rider?: RiderExperience | null;
    redirectEndpoint?: string | null;

    /**
     * Backward compatibility while older controller props are still around.
     */
    redirect_timeout?: number;
}

const props = defineProps<Props>();

const countdown = ref(0);
const isRedirecting = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;
let redirectTimer: ReturnType<typeof setTimeout> | null = null;

const riderContent = computed(() => props.rider?.success ?? null);
const riderRedirect = computed(() => props.rider?.redirect ?? null);

const hasRiderMessage = computed(() =>
    Boolean(riderContent.value?.enabled && riderContent.value?.content)
);

const hasRedirect = computed(() =>
    Boolean(riderRedirect.value?.enabled && props.redirectEndpoint)
);

const redirectTimeoutSeconds = computed(() => {
    const timeout = riderRedirect.value?.timeout ?? props.redirect_timeout ?? 10;

    return Math.max(0, Number(timeout) || 0);
});

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

const renderedMessage = computed(() => {
    const content = riderContent.value?.content;

    if (!hasRiderMessage.value || !content) {
        return null;
    }

    if (riderContent.value?.type === 'text') {
        return content.replace(/\n/g, '<br>');
    }

    try {
        return marked.parse(content) as string;
    } catch {
        return content.replace(/\n/g, '<br>');
    }
});

const handleRedirect = () => {
    if (!hasRedirect.value || !props.redirectEndpoint) {
        return;
    }

    isRedirecting.value = true;

    /**
     * Important:
     * Never redirect directly to rider.redirect.url.
     * Always go through the x-change/x-rider server-side redirect endpoint.
     */
    window.location.href = props.redirectEndpoint;
};

onMounted(() => {
    if (!hasRedirect.value) {
        return;
    }

    countdown.value = redirectTimeoutSeconds.value;

    if (redirectTimeoutSeconds.value <= 0) {
        handleRedirect();

        return;
    }

    countdownInterval = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);

        if (countdown.value <= 0 && countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }, 1000);

    redirectTimer = setTimeout(() => {
        handleRedirect();
    }, redirectTimeoutSeconds.value * 1000);
});

onBeforeUnmount(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }

    if (redirectTimer) {
        clearTimeout(redirectTimer);
    }
});
</script>

<template>
    <Head title="Claim Successful" />

    <div class="min-h-screen bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8">
        <Card class="mx-auto max-w-md border-0 bg-card/80 shadow-sm">
            <CardContent class="space-y-8 px-6 py-8">
                <!-- Hero -->
                <div class="space-y-4 pt-4 text-center">
                    <CheckCircle2
                        class="mx-auto h-16 w-16"
                        :class="isPending ? 'text-amber-500' : 'text-green-500'"
                    />

                    <!-- Rider message -->
                    <div v-if="hasRiderMessage" class="overflow-visible">
                        <div
                            v-html="renderedMessage"
                            class="prose prose-lg max-w-none text-center font-semibold dark:prose-invert"
                        />
                    </div>

                    <!-- No rider: amount is the hero -->
                    <template v-else>
                        <p v-if="hasNonZeroAmount" class="text-2xl font-bold tracking-tight text-foreground">
                            {{ formattedAmount }}
                        </p>
                        <p class="text-center text-lg font-medium text-foreground">
                            {{ fallbackTitle }}
                        </p>
                    </template>

                    <!-- Voucher code badge -->
                    <div
                        v-if="!hasRiderMessage"
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 font-mono text-sm font-semibold tracking-widest text-primary"
                    >
                        {{ voucher.code }}
                    </div>
                </div>

                <!-- Redirect with countdown -->
                <div v-if="hasRedirect && !isRedirecting" class="space-y-3">
                    <Button class="w-full rounded-full" @click="handleRedirect">
                        Continue Now
                        <ExternalLink :size="14" class="ml-1.5" />
                    </Button>
                    <p v-if="redirectTimeoutSeconds > 0" class="text-center text-[11px] text-gray-400 dark:text-gray-600">
                        Redirecting in {{ countdown }}s
                    </p>
                </div>

                <!-- Redirecting -->
                <p v-else-if="hasRedirect && isRedirecting" class="text-center text-sm text-muted-foreground">
                    Redirecting…
                </p>

                <!-- Default actions -->
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
