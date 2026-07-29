<script setup lang="ts">
import {
    ArrowLeft,
    ArrowRight,
    ExternalLink,
    FileText,
    LoaderCircle,
    RefreshCw,
    Route,
    ShieldCheck,
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

const steps = computed(() => props.manifest?.journey.steps ?? []);
const currentStep = computed(() => steps.value[currentStepIndex.value] ?? null);
const currentStepNumber = computed(() =>
    currentStep.value === null ? 0 : currentStepIndex.value + 1,
);

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
        class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-[1.15rem] bg-slate-950 text-white"
        data-testid="cockpit-claim-experience-preview"
    >
        <div
            class="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 px-4 py-3"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <Route class="size-4 text-cyan-300" aria-hidden="true" />
                    <h5 class="text-sm font-semibold">
                        Claim Experience Preview
                    </h5>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-cyan-300/10 px-2 py-0.5 text-[0.65rem] font-semibold text-cyan-200"
                    >
                        <ShieldCheck class="size-3" aria-hidden="true" />
                        Preview Only
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    Walk through what the recipient will see.
                </p>
            </div>

            <button
                v-if="status === 'ready'"
                type="button"
                class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-white/15 bg-white/5 px-2.5 text-xs font-semibold text-slate-200 transition hover:border-cyan-300/50 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="processing || !canGenerate"
                data-testid="cockpit-claim-experience-refresh"
                @click="emit('refresh')"
            >
                <LoaderCircle
                    v-if="processing"
                    class="size-3.5 animate-spin"
                    aria-hidden="true"
                />
                <RefreshCw v-else class="size-3.5" aria-hidden="true" />
                {{ stale ? 'Update Preview' : 'Refresh' }}
            </button>
        </div>

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
            class="grid min-h-0 min-w-0 flex-1 grid-rows-[auto_minmax(0,1fr)] gap-3 p-3 @lg:grid-cols-[minmax(11rem,0.65fr)_minmax(0,1.35fr)] @lg:grid-rows-1"
        >
            <aside
                class="flex min-h-0 min-w-0 flex-col rounded-xl border border-white/10 bg-white/5 p-3"
            >
                <div
                    class="min-h-0 flex-1 overflow-x-auto @lg:overflow-x-hidden @lg:overflow-y-auto"
                >
                    <p
                        class="text-[0.65rem] font-semibold tracking-[0.16em] text-slate-500 uppercase"
                    >
                        Recipient Journey
                    </p>
                    <ol class="mt-2 flex gap-1.5 @lg:block @lg:space-y-1.5">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.key"
                            class="shrink-0 @lg:shrink"
                        >
                            <button
                                type="button"
                                class="flex w-max min-w-32 items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition @lg:w-full @lg:min-w-0"
                                :class="
                                    index === currentStepIndex
                                        ? 'bg-cyan-300 text-slate-950'
                                        : 'text-slate-300 hover:bg-white/10'
                                "
                                :data-testid="`cockpit-claim-experience-step-${index + 1}`"
                                @click="currentStepIndex = index"
                            >
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded-full border text-[0.6rem] font-black"
                                    :class="
                                        index === currentStepIndex
                                            ? 'border-slate-950/20'
                                            : 'border-white/15'
                                    "
                                >
                                    {{ index + 1 }}
                                </span>
                                <span class="min-w-0 truncate font-semibold">
                                    {{ step.title }}
                                </span>
                            </button>
                        </li>
                    </ol>
                </div>

                <div
                    class="mt-3 flex items-center justify-between gap-2 border-t border-white/10 pt-3"
                >
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-lg border border-white/15 text-slate-200 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="currentStepIndex === 0"
                        aria-label="Previous claim step"
                        data-testid="cockpit-claim-experience-previous"
                        @click="selectPreviousStep"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                    </button>
                    <span class="text-[0.65rem] font-semibold text-slate-400">
                        Step {{ currentStepNumber }} of {{ steps.length }}
                    </span>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-lg border border-white/15 text-slate-200 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="currentStepIndex >= steps.length - 1"
                        aria-label="Next claim step"
                        data-testid="cockpit-claim-experience-next"
                        @click="selectNextStep"
                    >
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </aside>

            <article
                v-if="currentStep"
                class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-white/10 bg-slate-900"
            >
                <div
                    class="flex items-start justify-between gap-3 border-b border-white/10 px-3 py-2"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[0.6rem] font-bold tracking-[0.15em] text-cyan-300 uppercase"
                        >
                            {{ currentStep.phase }}
                        </p>
                        <p class="truncate text-xs font-semibold">
                            {{ currentStep.title }}
                        </p>
                    </div>
                    <span
                        v-if="stale"
                        class="shrink-0 rounded-full bg-amber-300/10 px-2 py-0.5 text-[0.6rem] font-semibold text-amber-200"
                    >
                        Design Changed
                    </span>
                </div>

                <div class="grid min-h-0 flex-1 place-items-center p-3">
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
                        class="grid max-w-sm place-items-center rounded-2xl border border-dashed border-cyan-300/25 bg-cyan-300/5 p-6 text-center"
                        data-testid="cockpit-claim-experience-concept"
                    >
                        <Route
                            class="size-8 text-cyan-300"
                            aria-hidden="true"
                        />
                        <p class="mt-3 text-sm font-semibold">
                            {{ currentStep.title }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">
                            {{ currentStep.description }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-if="status === 'ready' && manifest"
            class="flex flex-wrap items-center justify-between gap-2 border-t border-white/10 px-4 py-2 text-[0.65rem] text-slate-500"
        >
            <span>No claim submission, provider call, or money movement.</span>
            <div class="flex shrink-0 items-center gap-2">
                <a
                    v-if="manifest.exports.pdf_url"
                    :href="manifest.exports.pdf_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 font-semibold text-slate-300 hover:text-white"
                    data-testid="cockpit-claim-experience-pdf"
                >
                    <FileText class="size-3" aria-hidden="true" />
                    PDF
                    <ExternalLink class="size-2.5" aria-hidden="true" />
                </a>
                <a
                    v-if="manifest.exports.html_url"
                    :href="manifest.exports.html_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 font-semibold text-slate-300 hover:text-white"
                    data-testid="cockpit-claim-experience-html"
                >
                    HTML
                    <ExternalLink class="size-2.5" aria-hidden="true" />
                </a>
            </div>
        </div>
    </section>
</template>
