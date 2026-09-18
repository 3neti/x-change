<script setup lang="ts">
import { Minimize2 } from 'lucide-vue-next';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        src: string;
        alt: string;
        title: string;
        description: string;
        testId: string;
        returnLabel?: string;
        returnTestId?: string;
    }>(),
    { returnLabel: 'Back to stamp' },
);
defineEmits<{ restore: [] }>();
const returnButton = ref<HTMLButtonElement | null>(null);
defineExpose({ focus: () => returnButton.value?.focus() });
</script>

<template>
    <div
        class="flex min-w-0 flex-col rounded-3xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner dark:border-slate-800 dark:bg-slate-900/80"
        :data-testid="testId"
    >
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3 px-1">
            <div class="min-w-0">
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                >
                    {{ title }}
                </p>
                <p
                    class="break-words text-xs text-slate-600 dark:text-slate-300"
                >
                    {{ description }}
                </p>
            </div>
            <button
                ref="returnButton"
                type="button"
                class="inline-flex min-h-9 shrink-0 items-center justify-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                :data-testid="returnTestId ?? `${testId}-restore`"
                @click="$emit('restore')"
            >
                <Minimize2 class="size-3.5" aria-hidden="true" />{{
                    returnLabel
                }}
            </button>
        </div>
        <button
            type="button"
            class="flex flex-1 items-center justify-center rounded-[1.4rem] bg-white p-4 shadow-xl shadow-slate-900/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:p-6"
            :aria-label="returnLabel"
            aria-pressed="true"
            :data-testid="`${testId}-button`"
            @click="$emit('restore')"
        >
            <img
                :src="src"
                :alt="alt"
                class="aspect-square w-full max-w-2xl object-contain"
                width="1024"
                height="1024"
                decoding="async"
                :data-testid="`${testId}-image`"
            />
        </button>
    </div>
</template>
