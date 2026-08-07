<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    LoaderCircle,
    RefreshCw,
    Route,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { CockpitClaimExperiencePreviewManifest } from '../types';

const props = defineProps<{
    status: 'idle' | 'ready' | 'failed';
    processing: boolean;
    message: string;
    manifest: CockpitClaimExperiencePreviewManifest | null;
    stale?: boolean;
    canGenerate: boolean;
}>();

const emit = defineEmits<{
    generate: [];
    refresh: [];
}>();

const currentStepIndex = ref(0);

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
}

function selectNextStep(): void {
    currentStepIndex.value = Math.min(
        currentStepIndex.value + 1,
        Math.max(steps.value.length - 1, 0),
    );
}
</script>

<template>
    <section
        class="relative flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-[1.15rem] bg-slate-950 text-white"
        data-testid="cockpit-claim-experience-preview"
        aria-label="Claim experience visual walkthrough"
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

        <div v-else class="relative min-h-0 min-w-0 flex-1 overflow-hidden">
            <button
                type="button"
                class="absolute top-3 right-3 z-20 grid size-8 place-items-center rounded-full border border-white/15 bg-slate-950/75 text-slate-200 shadow-lg backdrop-blur transition hover:border-cyan-300/50 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="processing || !canGenerate"
                :aria-label="
                    stale ? 'Update claim preview' : 'Refresh claim preview'
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

            <article
                v-if="currentStep"
                class="grid h-full min-h-0 min-w-0 place-items-center overflow-hidden bg-slate-900 p-3"
            >
                <div
                    v-if="currentStep.frame"
                    class="h-full max-h-full w-auto max-w-full overflow-hidden rounded-[1.15rem] border-[5px] border-slate-700 bg-white shadow-2xl"
                >
                    <img
                        :src="currentStep.frame.url"
                        :alt="`${currentStep.title} claim preview`"
                        class="h-full max-h-full w-auto max-w-full object-contain"
                        data-testid="cockpit-claim-experience-frame"
                    />
                </div>
                <div
                    v-else
                    class="flex aspect-[9/16] h-full max-h-full min-h-0 w-auto max-w-full flex-col overflow-hidden rounded-[1.15rem] border-[5px] border-slate-700 bg-slate-50 text-slate-950 shadow-2xl"
                    data-testid="cockpit-claim-experience-concept"
                    :aria-label="currentStep.title"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
                    >
                        <span class="text-[10px] font-black tracking-[0.18em] text-slate-500 uppercase">
                            x-change
                        </span>
                        <span
                            class="rounded-full bg-cyan-100 px-2 py-1 text-[9px] font-bold tracking-wide text-cyan-900 uppercase"
                        >
                            {{ currentStep.phase }}
                        </span>
                    </div>
                    <div
                        class="flex min-h-0 flex-1 flex-col justify-center gap-4 px-5 py-6"
                    >
                        <div
                            class="grid size-10 place-items-center rounded-2xl bg-slate-950 text-cyan-300"
                        >
                            <Route class="size-5" aria-hidden="true" />
                        </div>
                        <div class="grid gap-2">
                            <p class="text-lg font-bold tracking-tight">
                                {{ currentStep.title }}
                            </p>
                            <p class="text-xs leading-5 text-slate-600">
                                {{ currentStep.description }}
                            </p>
                        </div>
                        <div class="grid gap-2" aria-hidden="true">
                            <div
                                class="h-9 rounded-xl border border-slate-200 bg-white"
                            />
                            <div
                                class="h-9 rounded-xl border border-slate-200 bg-white"
                            />
                        </div>
                        <div
                            class="grid min-h-10 place-items-center rounded-xl bg-slate-950 text-xs font-bold text-white"
                            aria-hidden="true"
                        >
                            Continue
                        </div>
                    </div>
                </div>
            </article>

            <div
                class="absolute inset-x-3 bottom-3 z-20 flex items-center justify-between gap-3 rounded-full border border-white/10 bg-slate-950/75 px-2 py-1.5 shadow-lg backdrop-blur"
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
                        @click="currentStepIndex = index"
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
    </section>
</template>
