<script setup lang="ts">
import { CircleHelp } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, useId, watch } from 'vue';

// Accessible tooltip with viewport-collision handling. The tooltip is
// teleported to <body> so it can be positioned with `position: fixed`
// and clamped to the viewport instead of being clipped by an ancestor
// or overflowing a narrow phone viewport. Because teleporting moves the
// tooltip out of this component's DOM subtree, visibility can no longer
// rely on Tailwind's CSS-only group-hover/group-focus-within convention
// (used elsewhere in Cockpit for same-subtree tooltips); this component
// tracks hover/focus explicitly instead and drives the same role="tooltip"
// + aria-describedby contract.
defineProps<{
    // Accessible name for the glyph itself, e.g. "About Amount".
    label: string;
    // Tooltip copy. Always rendered as plain text (never v-html), so any
    // server-provided fragments are passed through Vue's default escaping.
    tooltip: string;
}>();

const EDGE_ALLOWANCE = 8;
const TRIGGER_GAP = 6;

const tooltipId = useId();
const triggerEl = ref<HTMLElement | null>(null);
const tooltipEl = ref<HTMLElement | null>(null);
const hovered = ref(false);
const focused = ref(false);
const open = computed<boolean>(() => hovered.value || focused.value);
const position = reactive({ top: 0, left: 0 });

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function updatePosition(): void {
    if (
        typeof window === 'undefined' ||
        triggerEl.value === null ||
        tooltipEl.value === null
    ) {
        return;
    }

    const triggerRect = triggerEl.value.getBoundingClientRect();
    const tooltipRect = tooltipEl.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Prefer above the trigger; flip below only when there is not enough
    // room above to keep the required edge allowance.
    const fitsAbove =
        triggerRect.top - TRIGGER_GAP - tooltipRect.height >= EDGE_ALLOWANCE;
    const top = fitsAbove
        ? triggerRect.top - TRIGGER_GAP - tooltipRect.height
        : Math.min(
              triggerRect.bottom + TRIGGER_GAP,
              viewportHeight - tooltipRect.height - EDGE_ALLOWANCE,
          );

    const idealLeft =
        triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2;

    position.top = Math.max(top, EDGE_ALLOWANCE);
    position.left = clamp(
        idealLeft,
        EDGE_ALLOWANCE,
        viewportWidth - tooltipRect.width - EDGE_ALLOWANCE,
    );
}

watch(open, (isOpen) => {
    if (isOpen) {
        updatePosition();
    }
});

function handleMouseEnter(): void {
    hovered.value = true;
}

function handleMouseLeave(): void {
    hovered.value = false;
}

function handleFocus(): void {
    focused.value = true;
}

function handleBlur(): void {
    focused.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        hovered.value = false;
        focused.value = false;
    }
}

function handleViewportChange(): void {
    if (open.value) {
        updatePosition();
    }
}

onMounted((): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('resize', handleViewportChange);
    window.addEventListener('scroll', handleViewportChange, true);
});

onBeforeUnmount((): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('resize', handleViewportChange);
    window.removeEventListener('scroll', handleViewportChange, true);
});
</script>

<template>
    <span
        class="inline-flex shrink-0 align-middle"
        data-testid="cockpit-field-help"
    >
        <button
            ref="triggerEl"
            type="button"
            class="inline-grid size-4 place-items-center rounded-full text-slate-400 transition hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-slate-500 dark:hover:text-slate-300"
            :aria-label="label"
            :aria-describedby="tooltipId"
            data-testid="cockpit-field-help-trigger"
            @click.prevent
            @mouseenter="handleMouseEnter"
            @mouseleave="handleMouseLeave"
            @focus="handleFocus"
            @blur="handleBlur"
            @keydown="handleKeydown"
        >
            <CircleHelp class="size-3.5" aria-hidden="true" />
        </button>
        <Teleport to="body">
            <span
                ref="tooltipEl"
                :id="tooltipId"
                role="tooltip"
                class="pointer-events-none fixed z-50 w-max max-w-60 rounded-md bg-slate-950 px-2.5 py-1.5 text-left text-[0.65rem] leading-4 font-medium text-white shadow-xl transition-opacity dark:bg-white dark:text-slate-950"
                :class="open ? 'opacity-100' : 'opacity-0'"
                :style="{
                    top: `${position.top}px`,
                    left: `${position.left}px`,
                }"
                data-testid="cockpit-field-help-tooltip"
            >
                {{ tooltip }}
            </span>
        </Teleport>
    </span>
</template>
