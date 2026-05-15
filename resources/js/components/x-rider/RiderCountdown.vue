<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ExternalLink } from 'lucide-vue-next';

interface RiderRedirect {
    enabled: boolean;
    url?: string | null;
    timeout: number;
    fallbackUrl?: string | null;
    meta?: Record<string, unknown>;
}

const props = withDefaults(defineProps<{
    redirect?: RiderRedirect | null;
    redirectEndpoint?: string | null;
    continueLabel?: string;
}>(), {
    continueLabel: 'Continue Now',
});

const countdown = ref(0);
const isRedirecting = ref(false);

let countdownInterval: ReturnType<typeof setInterval> | null = null;
let redirectTimer: ReturnType<typeof setTimeout> | null = null;

const hasRedirect = computed(() =>
    Boolean(props.redirect?.enabled && props.redirectEndpoint)
);

const redirectTimeoutSeconds = computed(() => {
    const timeout = props.redirect?.timeout ?? 10;

    return Math.max(0, Number(timeout) || 0);
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
    <div v-if="hasRedirect && !isRedirecting" class="space-y-3">
        <Button class="w-full rounded-full" @click="handleRedirect">
            {{ continueLabel }}
            <ExternalLink :size="14" class="ml-1.5" />
        </Button>

        <p
            v-if="redirectTimeoutSeconds > 0"
            class="text-center text-[11px] text-gray-400 dark:text-gray-600"
        >
            Redirecting in {{ countdown }}s
        </p>
    </div>

    <p
        v-else-if="hasRedirect && isRedirecting"
        class="text-center text-sm text-muted-foreground"
    >
        Redirecting…
    </p>
</template>
