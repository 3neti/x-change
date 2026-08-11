<script setup lang="ts">
import { CircleHelp } from 'lucide-vue-next';
import { useId } from 'vue';

// Reuses the same hover+focus tooltip convention already established in
// CockpitPayCodeIndicator.vue and the Rider artwork radio tooltips: a
// `group` wrapper, a `role="tooltip"` sibling revealed via
// group-hover/group-focus-within, and an aria-describedby link so the
// content is available to assistive tech regardless of visual state.
defineProps<{
    // Accessible name for the glyph itself, e.g. "About Amount".
    label: string;
    // Tooltip copy. Always rendered as plain text (never v-html), so any
    // server-provided fragments are passed through Vue's default escaping.
    tooltip: string;
}>();

const tooltipId = useId();
</script>

<template>
    <span
        class="group/field-help relative inline-flex shrink-0 align-middle"
        data-testid="cockpit-field-help"
    >
        <button
            type="button"
            class="inline-grid size-4 place-items-center rounded-full text-slate-400 transition hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-slate-500 dark:hover:text-slate-300"
            :aria-label="label"
            :aria-describedby="tooltipId"
            data-testid="cockpit-field-help-trigger"
            @click.prevent
        >
            <CircleHelp class="size-3.5" aria-hidden="true" />
        </button>
        <span
            :id="tooltipId"
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-0 z-30 mb-1.5 w-max max-w-60 rounded-md bg-slate-950 px-2.5 py-1.5 text-left text-[0.65rem] leading-4 font-medium text-white opacity-0 shadow-xl transition-opacity group-hover/field-help:opacity-100 group-focus-within/field-help:opacity-100 dark:bg-white dark:text-slate-950"
            data-testid="cockpit-field-help-tooltip"
        >
            {{ tooltip }}
        </span>
    </span>
</template>
