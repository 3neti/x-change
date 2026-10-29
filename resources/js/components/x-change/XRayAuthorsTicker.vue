<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    authors: string[];
    active: boolean;
}>();

const authorIndex = ref(0);
const reducedMotion = ref(false);
const currentAuthor = computed(() => props.authors[authorIndex.value] ?? props.authors[0] ?? '');
let timer: ReturnType<typeof setTimeout> | undefined;
let motionPreference: MediaQueryList | undefined;

function clearCycle(): void {
    if (timer !== undefined) {
        clearTimeout(timer);
        timer = undefined;
    }
}

function scheduleNext(): void {
    if (!props.active || reducedMotion.value || props.authors.length < 2) {
        return;
    }

    timer = setTimeout(() => {
        authorIndex.value = (authorIndex.value + 1) % props.authors.length;
        scheduleNext();
    }, authorIndex.value === 0 ? 5000 : 2500);
}

watch(
    [() => props.authors, () => props.active, reducedMotion],
    () => {
        clearCycle();
        authorIndex.value = 0;
        scheduleNext();
    },
    { immediate: true },
);

onMounted(() => {
    if (typeof window.matchMedia !== 'function') {
        return;
    }

    motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    reducedMotion.value = motionPreference.matches;
    motionPreference.addEventListener('change', updateMotionPreference);
});

function updateMotionPreference(event: MediaQueryListEvent): void {
    reducedMotion.value = event.matches;
}

onBeforeUnmount(() => {
    clearCycle();
    motionPreference?.removeEventListener('change', updateMotionPreference);
});
</script>

<template>
    <dd class="min-w-0 flex-1 text-right font-medium text-foreground" data-testid="xray-authors">
        <span class="sr-only">{{ authors.join(', ') }}</span>
        <span v-if="reducedMotion" aria-hidden="true" class="break-words">{{ authors.join(' · ') }}</span>
        <span v-else aria-hidden="true" class="relative block h-5 overflow-hidden">
            <Transition name="xray-author">
                <span :key="authorIndex" class="absolute inset-0 block truncate text-right" data-testid="xray-current-author">
                    {{ currentAuthor }}
                </span>
            </Transition>
        </span>
    </dd>
</template>

<style scoped>
.xray-author-enter-active,
.xray-author-leave-active {
    transition: transform 700ms ease-in-out, opacity 700ms ease-in-out;
}

.xray-author-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.xray-author-leave-to {
    transform: translateX(-100%);
    opacity: 0;
}
</style>
