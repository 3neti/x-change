<script setup lang="ts">
import { computed, useId } from 'vue';
import { resolvePayCodeIndicator } from '../payCodeIndicators';

const props = withDefaults(
    defineProps<{
        indicatorKey: string;
        tooltip?: string;
        tone?: 'light' | 'dark';
        size?: 'sm' | 'md';
    }>(),
    {
        tooltip: '',
        tone: 'light',
        size: 'md',
    },
);

const indicator = computed(() => resolvePayCodeIndicator(props.indicatorKey));
const tooltipId = useId();
const tooltipCopy = computed(
    () => props.tooltip.trim() || indicator.value.tooltip,
);
</script>

<template>
    <span
        class="group/indicator relative inline-flex shrink-0"
        data-testid="cockpit-pay-code-indicator"
        :data-indicator-key="indicator.key"
    >
        <span
            tabindex="0"
            role="img"
            class="inline-grid place-items-center rounded-full border transition focus-visible:outline-2 focus-visible:outline-offset-2"
            :class="[
                size === 'sm' ? 'size-5' : 'size-7',
                tone === 'dark'
                    ? 'border-white/15 bg-white/10 text-emerald-200 focus-visible:outline-emerald-300'
                    : 'border-emerald-700/20 bg-emerald-700 text-white shadow-sm focus-visible:outline-emerald-700 dark:border-emerald-300/20 dark:bg-emerald-500 dark:text-slate-950 dark:focus-visible:outline-emerald-300',
            ]"
            :aria-label="indicator.label"
            :aria-describedby="tooltipId"
        >
            <component
                :is="indicator.icon"
                :class="size === 'sm' ? 'size-3' : 'size-3.5'"
                aria-hidden="true"
            />
        </span>
        <span
            :id="tooltipId"
            role="tooltip"
            class="pointer-events-none absolute z-50 w-max max-w-52 rounded-md bg-slate-950 px-2.5 py-1.5 text-center text-[0.65rem] leading-4 font-medium text-white opacity-0 shadow-xl transition-opacity group-focus-within/indicator:opacity-100 group-hover/indicator:opacity-100"
            :class="
                tone === 'dark'
                    ? 'bottom-full left-1/2 mb-2 -translate-x-1/2'
                    : 'top-full right-0 mt-2'
            "
            data-testid="cockpit-pay-code-indicator-tooltip"
        >
            {{ tooltipCopy }}
        </span>
    </span>
</template>
