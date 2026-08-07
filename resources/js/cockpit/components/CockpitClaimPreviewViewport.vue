<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CockpitClaimExperiencePreviewStep } from '../types';

const props = withDefaults(
    defineProps<{
        step: CockpitClaimExperiencePreviewStep;
        viewport: {
            profile: string;
            width: number;
            height: number;
        };
        presentation?: 'inline' | 'expanded';
    }>(),
    {
        presentation: 'inline',
    },
);

const container = ref<HTMLElement | null>(null);
const scale = ref(1);
let resizeObserver: ResizeObserver | null = null;

const viewportWidth = computed(() => Math.max(props.viewport.width, 1));
const viewportHeight = computed(() => Math.max(props.viewport.height, 1));
const scaledFrameStyle = computed(() => ({
    width: `${viewportWidth.value * scale.value}px`,
    height: `${viewportHeight.value * scale.value}px`,
}));
const frameStyle = computed(() => ({
    width: `${viewportWidth.value}px`,
    height: `${viewportHeight.value}px`,
    transform: `scale(${scale.value})`,
}));

function measure(): void {
    if (!container.value) return;

    const horizontalPadding = props.presentation === 'expanded' ? 32 : 16;
    const verticalPadding = props.presentation === 'expanded' ? 32 : 16;
    const availableWidth = Math.max(
        container.value.clientWidth - horizontalPadding,
        1,
    );
    const availableHeight = Math.max(
        container.value.clientHeight - verticalPadding,
        1,
    );

    scale.value = Math.min(
        availableWidth / viewportWidth.value,
        availableHeight / viewportHeight.value,
        1,
    );
}

onMounted(() => {
    measure();

    if (typeof ResizeObserver !== 'undefined' && container.value) {
        resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(container.value);
    }
});

onBeforeUnmount(() => resizeObserver?.disconnect());

watch(
    () => [props.viewport.width, props.viewport.height, props.presentation],
    async () => {
        await nextTick();
        measure();
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
            class="relative shrink-0 overflow-hidden rounded-[1.6rem] border-[6px] border-slate-700 bg-slate-950 shadow-2xl"
            :style="scaledFrameStyle"
            data-testid="cockpit-claim-preview-device"
        >
            <iframe
                :key="step.preview_url"
                :src="step.preview_url"
                :title="`${step.title} claim preview`"
                :width="viewportWidth"
                :height="viewportHeight"
                class="absolute top-0 left-0 border-0 bg-slate-950"
                :style="frameStyle"
                sandbox="allow-scripts allow-same-origin"
                loading="eager"
                data-testid="cockpit-claim-preview-iframe"
            />
        </div>
    </div>
</template>
