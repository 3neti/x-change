<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    value?: string | null;
    label?: string;
    unavailableLabel?: string;
}>(), {
    label: 'Copy URL',
    unavailableLabel: 'Copy unavailable',
});

const copyState = ref<'idle' | 'copied' | 'failed' | 'unavailable'>('idle');
const copyValue = computed(() => {
    if (typeof props.value === 'string' && props.value.trim() !== '') {
        return props.value.trim();
    }

    return null;
});
const canCopy = computed(() => copyValue.value !== null);
const buttonLabel = computed(() => {
    if (!canCopy.value) {
        return props.unavailableLabel;
    }

    if (copyState.value === 'copied') {
        return 'Copied';
    }

    if (copyState.value === 'failed') {
        return 'Copy failed';
    }

    return props.label;
});
const statusText = computed(() => {
    if (!canCopy.value || copyState.value === 'unavailable') {
        return 'Copy unavailable in this browser context.';
    }

    if (copyState.value === 'copied') {
        return 'Copied locally. No delivery was sent.';
    }

    if (copyState.value === 'failed') {
        return 'Copy failed locally. No backend call was made.';
    }

    return 'Browser-local copy only. No delivery will be sent.';
});

async function copyToClipboard(): Promise<void> {
    if (!copyValue.value) {
        copyState.value = 'unavailable';

        return;
    }

    const clipboard = globalThis.navigator?.clipboard;

    if (!clipboard?.writeText) {
        copyState.value = 'unavailable';

        return;
    }

    try {
        await clipboard.writeText(copyValue.value);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'failed';
    }
}
</script>

<template>
    <div class="space-y-2" data-testid="cockpit-manual-copy-control">
        <button
            type="button"
            :disabled="!canCopy"
            class="inline-flex items-center rounded-full border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:border-slate-300 disabled:text-slate-400 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900/50 dark:disabled:border-slate-700 dark:disabled:text-slate-500"
            data-testid="cockpit-manual-copy-button"
            @click="copyToClipboard"
        >
            {{ buttonLabel }}
        </button>
        <p
            class="text-xs leading-5 text-slate-500 dark:text-slate-400"
            data-testid="cockpit-manual-copy-status"
        >
            {{ statusText }}
        </p>
    </div>
</template>
