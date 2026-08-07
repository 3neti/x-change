<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    LoaderCircle,
    Maximize2,
    RefreshCw,
    Route,
    Sparkles,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { CockpitClaimExperiencePreviewManifest } from '../types';
import CockpitClaimPreviewViewport from './CockpitClaimPreviewViewport.vue';

const props = withDefaults(
    defineProps<{
        status: 'idle' | 'ready' | 'failed';
        processing: boolean;
        message: string;
        manifest: CockpitClaimExperiencePreviewManifest | null;
        stale?: boolean;
        canGenerate: boolean;
        safePresentation?: boolean;
        autoplay?: boolean;
        autoplayIntervalMs?: number;
    }>(),
    {
        safePresentation: false,
        autoplay: false,
        autoplayIntervalMs: 3800,
    },
);

const emit = defineEmits<{
    generate: [];
    refresh: [];
}>();

const currentStepIndex = ref(0);
const expanded = ref(false);
const expandedDialog = ref<HTMLElement | null>(null);
const autoplayPaused = ref(false);
let autoplayTimer: ReturnType<typeof setInterval> | null = null;

const steps = computed(() =>
    (props.manifest?.journey.steps ?? []).filter(
        (step) => step.key !== 'xray-preview',
    ),
);
const currentStep = computed(() => steps.value[currentStepIndex.value] ?? null);

watch(
    () => props.manifest?.fingerprint,
    () => {
        currentStepIndex.value = 0;
        scheduleAutoplay();
    },
);

watch(
    () => steps.value.length,
    (stepCount) => {
        currentStepIndex.value = Math.min(
            currentStepIndex.value,
            Math.max(stepCount - 1, 0),
        );
    },
);

function selectPreviousStep(): void {
    currentStepIndex.value = Math.max(currentStepIndex.value - 1, 0);
    scheduleAutoplay();
}

function selectNextStep(): void {
    currentStepIndex.value = Math.min(
        currentStepIndex.value + 1,
        Math.max(steps.value.length - 1, 0),
    );
    scheduleAutoplay();
}

function selectStep(index: number): void {
    currentStepIndex.value = index;
    scheduleAutoplay();
}

function clearAutoplay(): void {
    if (autoplayTimer !== null) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
}

function scheduleAutoplay(): void {
    clearAutoplay();

    const reducedMotion =
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (
        !props.autoplay ||
        autoplayPaused.value ||
        reducedMotion ||
        props.status !== 'ready' ||
        steps.value.length < 2
    ) {
        return;
    }

    autoplayTimer = setInterval(
        () => {
            currentStepIndex.value =
                (currentStepIndex.value + 1) % steps.value.length;
        },
        Math.max(props.autoplayIntervalMs, 1000),
    );
}

function pauseAutoplay(): void {
    autoplayPaused.value = true;
    clearAutoplay();
}

function resumeAutoplay(): void {
    autoplayPaused.value = false;
    scheduleAutoplay();
}

async function openExpandedPreview(): Promise<void> {
    expanded.value = true;
    await nextTick();
    expandedDialog.value?.focus();
}

function closeExpandedPreview(): void {
    expanded.value = false;
}

onMounted(scheduleAutoplay);
onBeforeUnmount(clearAutoplay);

watch(
    () => [
        props.autoplay,
        props.autoplayIntervalMs,
        props.status,
        steps.value.length,
    ],
    scheduleAutoplay,
);
</script>

<template>
    <section
        class="relative flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-[1.15rem] bg-slate-950 text-white"
        data-testid="cockpit-claim-experience-preview"
        aria-label="Claim experience visual walkthrough"
        @mouseenter="pauseAutoplay"
        @mouseleave="resumeAutoplay"
        @focusin="pauseAutoplay"
        @focusout="resumeAutoplay"
    >
        <div
            v-if="status !== 'ready' || manifest === null"
            class="grid min-h-0 flex-1 place-items-center p-6 text-center"
        >
            <div class="max-w-sm">
                <div
                    class="mx-auto grid size-12 place-items-center rounded-2xl bg-cyan-300/10 text-cyan-200"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="size-6 animate-spin"
                        aria-hidden="true"
                    />
                    <Sparkles v-else class="size-6" aria-hidden="true" />
                </div>
                <p class="mt-3 text-sm font-semibold">
                    {{
                        processing
                            ? 'Rendering the claim journey…'
                            : status === 'failed'
                              ? 'Preview unavailable'
                              : 'See the claim before you issue'
                    }}
                </p>
                <p class="mt-1 text-xs leading-5 text-slate-400">
                    {{ message }}
                </p>
                <button
                    v-if="!processing"
                    type="button"
                    class="mt-4 inline-flex min-h-9 items-center gap-2 rounded-xl bg-cyan-300 px-3.5 text-xs font-bold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canGenerate"
                    data-testid="cockpit-claim-experience-generate"
                    @click="emit('generate')"
                >
                    <Route class="size-3.5" aria-hidden="true" />
                    {{
                        status === 'failed'
                            ? 'Try Again'
                            : 'Generate Walkthrough'
                    }}
                </button>
            </div>
        </div>

        <div
            v-else
            class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
        >
            <header
                class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-3 py-2"
            >
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-white">
                        {{ currentStep?.title }}
                    </p>
                    <p class="text-[10px] text-slate-400">
                        Step {{ currentStepIndex + 1 }} of {{ steps.length }}
                    </p>
                </div>
                <span
                    v-if="safePresentation"
                    class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-emerald-200"
                >
                    Safe demo
                </span>
                <div v-else class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-full border border-white/15 text-slate-200 transition hover:border-cyan-300/50 hover:text-white"
                        aria-label="Open full claim preview"
                        data-testid="cockpit-claim-experience-expand"
                        @click="openExpandedPreview"
                    >
                        <Maximize2 class="size-3.5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-full border border-white/15 text-slate-200 transition hover:border-cyan-300/50 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="processing || !canGenerate"
                        :aria-label="
                            stale
                                ? 'Update claim preview'
                                : 'Refresh claim preview'
                        "
                        data-testid="cockpit-claim-experience-refresh"
                        @click="emit('refresh')"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="size-3.5 animate-spin"
                            aria-hidden="true"
                        />
                        <RefreshCw v-else class="size-3.5" aria-hidden="true" />
                    </button>
                </div>
            </header>

            <Transition
                mode="out-in"
                enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="translate-x-2 opacity-0 motion-reduce:transform-none"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="-translate-x-2 opacity-0 motion-reduce:transform-none"
            >
                <article
                    v-if="currentStep"
                    :key="currentStep.key"
                    class="min-h-0 min-w-0 flex-1 overflow-hidden bg-slate-100 dark:bg-slate-900"
                >
                    <CockpitClaimPreviewViewport
                        :step="currentStep"
                        :viewport="manifest.journey.viewport"
                        :safe-presentation="safePresentation"
                    />
                </article>
            </Transition>

            <div
                class="flex shrink-0 items-center justify-between gap-3 border-t border-white/10 bg-slate-950 px-3 py-2"
            >
                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-full text-slate-200 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30"
                    :disabled="currentStepIndex === 0"
                    aria-label="Previous claim step"
                    data-testid="cockpit-claim-experience-previous"
                    @click="selectPreviousStep"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                </button>
                <div class="flex min-w-0 items-center justify-center gap-1.5">
                    <button
                        v-for="(step, index) in steps"
                        :key="step.key"
                        type="button"
                        class="size-1.5 rounded-full transition"
                        :class="
                            index === currentStepIndex
                                ? 'bg-cyan-300'
                                : 'bg-white/25 hover:bg-white/45'
                        "
                        :aria-label="`Claim step ${index + 1}: ${step.title}`"
                        :data-testid="`cockpit-claim-experience-step-${index + 1}`"
                        @click="selectStep(index)"
                    />
                </div>
                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-full text-slate-200 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30"
                    :disabled="currentStepIndex >= steps.length - 1"
                    aria-label="Next claim step"
                    data-testid="cockpit-claim-experience-next"
                    @click="selectNextStep"
                >
                    <ArrowRight class="size-4" aria-hidden="true" />
                </button>
            </div>
        </div>

        <Teleport v-if="expanded && currentStep" to="body">
            <div
                class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/80 backdrop-blur-sm sm:p-4"
                data-testid="cockpit-claim-experience-dialog-backdrop"
                @click.self="closeExpandedPreview"
                @keydown.esc.prevent="closeExpandedPreview"
            >
                <section
                    ref="expandedDialog"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="claim-preview-dialog-title"
                    class="flex h-dvh min-h-0 w-screen flex-col overflow-hidden bg-slate-950 text-white outline-none sm:h-[calc(100dvh-2rem)] sm:w-[calc(100vw-2rem)] sm:max-w-7xl sm:rounded-3xl sm:border sm:border-white/10 sm:shadow-2xl"
                    data-testid="cockpit-claim-experience-dialog"
                >
                    <header
                        class="flex shrink-0 items-center justify-between gap-4 border-b border-white/10 px-4 py-3 sm:px-5"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.16em] text-cyan-300"
                            >
                                Claim Experience
                            </p>
                            <h2
                                id="claim-preview-dialog-title"
                                class="truncate text-base font-semibold sm:text-lg"
                            >
                                {{ currentStep.title }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="grid size-10 shrink-0 place-items-center rounded-full border border-white/15 text-slate-200 transition hover:border-cyan-300/50 hover:text-white"
                            aria-label="Close full claim preview"
                            data-testid="cockpit-claim-experience-dialog-close"
                            @click="closeExpandedPreview"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </header>

                    <div
                        class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_18rem]"
                    >
                        <div class="min-h-0 overflow-hidden bg-slate-900">
                            <CockpitClaimPreviewViewport
                                :step="currentStep"
                                :viewport="manifest.journey.viewport"
                                presentation="expanded"
                                :safe-presentation="safePresentation"
                            />
                        </div>

                        <aside
                            class="max-h-36 overflow-x-auto border-t border-white/10 bg-slate-950 p-3 lg:max-h-none lg:overflow-y-auto lg:border-l lg:border-t-0 lg:p-4"
                            aria-label="Claim preview steps"
                        >
                            <div
                                class="flex min-w-max gap-2 lg:grid lg:min-w-0 lg:gap-2"
                            >
                                <button
                                    v-for="(step, index) in steps"
                                    :key="step.key"
                                    type="button"
                                    class="flex w-48 items-start gap-3 rounded-xl border px-3 py-2.5 text-left transition lg:w-full"
                                    :class="
                                        index === currentStepIndex
                                            ? 'border-cyan-300/60 bg-cyan-300/10 text-white'
                                            : 'border-white/10 text-slate-400 hover:border-white/25 hover:text-white'
                                    "
                                    :data-testid="`cockpit-claim-experience-dialog-step-${index + 1}`"
                                    @click="selectStep(index)"
                                >
                                    <span
                                        class="grid size-6 shrink-0 place-items-center rounded-full bg-white/10 text-[10px] font-bold"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-semibold"
                                        >
                                            {{ step.title }}
                                        </span>
                                        <span class="mt-0.5 block text-[10px]">
                                            {{
                                                step.phase.replaceAll('_', ' ')
                                            }}
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </Teleport>
    </section>
</template>
