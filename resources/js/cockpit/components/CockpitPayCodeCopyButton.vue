<script setup lang="ts">
import { Check, Copy, TriangleAlert } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ code: string; compact?: boolean }>();
const state = ref<'idle' | 'copied' | 'failed'>('idle');

async function copyCode(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.code);
        state.value = 'copied';
    } catch {
        state.value = 'failed';
    }

    window.setTimeout(() => {
        state.value = 'idle';
    }, 2_000);
}
</script>

<template>
    <button
        type="button"
        class="inline-flex h-8 items-center justify-center gap-1 rounded-full border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
        :aria-label="`Copy Pay Code ${code}`"
        :title="state === 'failed' ? 'Copy failed' : 'Copy Pay Code'"
        data-testid="cockpit-pay-code-copy-button"
        @click="copyCode"
    >
        <Check v-if="state === 'copied'" aria-hidden="true" class="size-3.5 text-emerald-600" />
        <TriangleAlert v-else-if="state === 'failed'" aria-hidden="true" class="size-3.5 text-rose-600" />
        <Copy v-else aria-hidden="true" class="size-3.5" />
        <span :class="compact && state === 'idle' ? 'sr-only' : ''" aria-live="polite">
            {{ state === 'copied' ? 'Copied' : state === 'failed' ? 'Failed' : 'Copy' }}
        </span>
    </button>
</template>
