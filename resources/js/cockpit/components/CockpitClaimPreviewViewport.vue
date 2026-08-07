<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { CockpitClaimExperiencePreviewStep } from '../types';
import CockpitClaimLiveScreen from './CockpitClaimLiveScreen.vue';

const props = withDefaults(
    defineProps<{
        step: CockpitClaimExperiencePreviewStep;
        viewport: {
            profile: string;
            width: number;
            height: number;
        };
        presentation?: 'inline' | 'expanded';
        safePresentation?: boolean;
    }>(),
    {
        presentation: 'inline',
        safePresentation: false,
    },
);

const container = ref<HTMLElement | null>(null);
const scale = ref(1);
let resizeObserver: ResizeObserver | null = null;
let intersectionObserver: IntersectionObserver | null = null;
let measurementFrame: number | null = null;

const viewportWidth = computed(() => Math.max(props.viewport.width, 1));
const viewportHeight = computed(() => Math.max(props.viewport.height, 1));
const deviceBorderWidth = computed(() =>
    props.presentation === 'expanded' ? 6 : 3,
);
const scaledFrameStyle = computed(() => ({
    width: `${viewportWidth.value * scale.value}px`,
    height: `${viewportHeight.value * scale.value}px`,
    borderWidth: `${deviceBorderWidth.value}px`,
}));
const frameStyle = computed(() => ({
    width: `${viewportWidth.value}px`,
    height: `${viewportHeight.value}px`,
    transform: `scale(${scale.value})`,
    transformOrigin: 'top left',
}));

function measure(): void {
    if (!container.value) return;

    const horizontalPadding = props.presentation === 'expanded' ? 32 : 16;
    const verticalPadding = props.presentation === 'expanded' ? 32 : 16;
    const availableWidth = Math.max(
        container.value.clientWidth -
            horizontalPadding -
            deviceBorderWidth.value * 2,
        1,
    );
    const availableHeight = Math.max(
        container.value.clientHeight -
            verticalPadding -
            deviceBorderWidth.value * 2,
        1,
    );

    scale.value = Math.min(
        availableWidth / viewportWidth.value,
        availableHeight / viewportHeight.value,
        1,
    );
}

function scheduleMeasurement(): void {
    if (measurementFrame !== null) {
        cancelAnimationFrame(measurementFrame);
    }

    measurementFrame = requestAnimationFrame(() => {
        measurementFrame = null;
        measure();
    });
}

onMounted(() => {
    scheduleMeasurement();

    if (typeof ResizeObserver !== 'undefined' && container.value) {
        resizeObserver = new ResizeObserver(scheduleMeasurement);
        resizeObserver.observe(container.value);
    }

    if (typeof IntersectionObserver !== 'undefined' && container.value) {
        intersectionObserver = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                scheduleMeasurement();
            }
        });
        intersectionObserver.observe(container.value);
    }

    window.addEventListener('resize', scheduleMeasurement);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    intersectionObserver?.disconnect();
    window.removeEventListener('resize', scheduleMeasurement);

    if (measurementFrame !== null) {
        cancelAnimationFrame(measurementFrame);
    }
});

watch(
    () => [props.viewport.width, props.viewport.height, props.presentation],
    async () => {
        await nextTick();
        scheduleMeasurement();
    },
);
</script>

<template>
    <div
        ref="container"
        class="grid size-full min-h-0 min-w-0 place-items-center overflow-hidden"
        :class="presentation === 'expanded' ? 'p-4' : 'p-2'"
        data-testid="cockpit-claim-preview-viewport"
        :data-presentation="presentation"
        :data-viewport-profile="viewport.profile"
    >
        <div
            class="relative box-content shrink-0 overflow-hidden rounded-[1.6rem] border-slate-700 bg-white shadow-2xl dark:bg-slate-950"
            :style="scaledFrameStyle"
            data-testid="cockpit-claim-preview-device"
        >
            <div
                v-if="safePresentation && step.screen"
                :key="`${step.key}-safe-presentation`"
                class="absolute left-0 top-0 bg-white"
                :style="frameStyle"
                data-testid="cockpit-claim-preview-safe-screen"
            >
                <CockpitClaimLiveScreen
                    :screen="step.screen"
                    presentation="viewport"
                />
            </div>
            <iframe
                v-else
                :key="step.preview_url"
                :src="step.preview_url"
                :title="`${step.title} claim preview`"
                :width="viewportWidth"
                :height="viewportHeight"
                class="absolute left-0 top-0 border-0 bg-slate-950"
                :style="frameStyle"
                sandbox="allow-scripts allow-same-origin"
                loading="eager"
                data-testid="cockpit-claim-preview-iframe"
                @load="scheduleMeasurement"
            />
        </div>
    </div>
</template>
