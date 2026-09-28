<script setup lang="ts">
import type { CockpitFundingMethodSelectorReadModel } from '../types';

defineProps<{
    reference: CockpitFundingMethodSelectorReadModel['bank_transfer']['reconciliation_reference'];
    copied?: boolean;
}>();

const emit = defineEmits<{
    copy: [value: string];
}>();
</script>

<template>
    <section
        v-if="reference.mode !== 'disabled'"
        class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
        data-testid="bank-transfer-reconciliation-reference"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-slate-500">
                    {{ reference.label }}
                    <span v-if="reference.mode === 'required'">· Required</span>
                    <span v-else>· Optional</span>
                </p>
                <p
                    v-if="reference.value"
                    class="mt-1 font-mono text-base font-semibold tracking-wide"
                    data-testid="bank-transfer-reconciliation-reference-value"
                >
                    {{ reference.value }}
                </p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                    {{ reference.instructions }}
                </p>
            </div>
            <button
                v-if="reference.value"
                type="button"
                class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                data-testid="copy-bank-transfer-reconciliation-reference"
                @click="emit('copy', reference.value)"
            >
                {{ copied ? 'Copied' : 'Copy reference' }}
            </button>
        </div>
    </section>
</template>
