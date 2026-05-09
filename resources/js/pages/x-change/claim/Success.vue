<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ExternalLink, CheckCircle2 } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import { marked } from 'marked';

const routes = useXChangeRoutes();

interface Props {
    voucher: {
        code: string;
        amount: number;
        formatted_amount: string;
        currency: string;
    };
    rider?: {
        message?: string | null;
        url?: string | null;
    };
    redirect_timeout?: number;
}

const props = defineProps<Props>();

const countdown = ref(0);
const isRedirecting = ref(false);

const hasRiderUrl = computed(() => !!props.rider?.url);
const hasRiderMessage = computed(() => !!props.rider?.message);
const hasNonZeroAmount = computed(() => (props.voucher.amount ?? 0) > 0);

const renderedMessage = computed(() => {
    if (!hasRiderMessage.value || !props.rider?.message) return null;
    try {
        return marked.parse(props.rider.message) as string;
    } catch {
        return props.rider.message.replace(/\n/g, '<br>');
    }
});

const handleRedirect = () => {
    if (!hasRiderUrl.value) return;
    isRedirecting.value = true;
    // Use server-side redirect for tracking
    window.location.href = routes.claim.redirect(props.voucher.code);
};

onMounted(() => {
    if (hasRiderUrl.value) {
        const timeout = (props.redirect_timeout ?? 10) * 1000;
        countdown.value = Math.ceil(timeout / 1000);

        const interval = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) clearInterval(interval);
        }, 1000);

        setTimeout(() => {
            handleRedirect();
        }, timeout);
    }
});
</script>

<template>
    <Head title="Claim Successful" />

    <div class="min-h-screen bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8">
        <div class="mx-auto max-w-md space-y-8">
            <!-- Hero -->
            <div class="space-y-4 pt-4 text-center">
                <CheckCircle2 class="mx-auto h-16 w-16 text-green-500" />

                <!-- Rider message (prominent) -->
                <div v-if="hasRiderMessage" class="overflow-visible">
                    <div
                        v-html="renderedMessage"
                        class="prose prose-lg max-w-none text-center font-semibold dark:prose-invert"
                    />
                </div>
                <!-- No rider: amount is the hero -->
                <template v-else>
                    <p v-if="hasNonZeroAmount" class="text-2xl font-bold tracking-tight text-foreground">
                        {{ voucher.formatted_amount }}
                    </p>
                    <p class="text-center text-lg font-medium text-foreground">
                        {{ hasNonZeroAmount ? 'Disbursed to your account' : 'Pay Code claimed' }}
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
            <div v-if="hasRiderUrl && !isRedirecting" class="space-y-3">
                <Button class="w-full rounded-full" @click="handleRedirect">
                    Continue Now
                    <ExternalLink :size="14" class="ml-1.5" />
                </Button>
                <p class="text-center text-[11px] text-gray-400 dark:text-gray-600">
                    Redirecting in {{ countdown }}s
                </p>
            </div>

            <!-- Redirecting -->
            <p v-else-if="hasRiderUrl && isRedirecting" class="text-center text-sm text-muted-foreground">
                Redirecting…
            </p>

            <!-- Default actions (no rider URL) -->
            <div v-else class="space-y-3">
                <Button class="w-full rounded-full" @click="router.visit('/x/claim')">
                    Redeem Another
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
        </div>
    </div>
</template>
