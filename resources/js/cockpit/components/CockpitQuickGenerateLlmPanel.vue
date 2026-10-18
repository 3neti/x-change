<script setup lang="ts">
import type { PayCodeStampPreviewState } from "../payCodeStampPreview";
import { Sparkles } from "lucide-vue-next";
import CockpitQuickGeneratePromptComposer from "./CockpitQuickGeneratePromptComposer.vue";
import CockpitQuickGenerateSurfaceSwitch from "./CockpitQuickGenerateSurfaceSwitch.vue";
import type { CockpitQuickGenerateSurface } from "./CockpitQuickGenerateSurfaceSwitch.vue";

withDefaults(
  defineProps<{
    stampPreview?: PayCodeStampPreviewState | null;
    issuanceSurface?: CockpitQuickGenerateSurface;
    composerView?: "prompt" | "standard";
    promptComposerEndpoint?: string;
    availableSurfaces?: CockpitQuickGenerateSurface[];
  }>(),
  {
    issuanceSurface: "llm",
    composerView: "prompt",
    availableSurfaces: () => ["composer", "llm", "pos", "qr"],
  },
);

const emit = defineEmits<{
  "update:issuanceSurface": [value: CockpitQuickGenerateSurface];
  interpreted: [instructions: Record<string, unknown>];
  activate: [instructions: Record<string, unknown>];
  "show-cost": [];
  "show-stamp": [];
}>();
</script>

<template>
  <section
    class="space-y-4 rounded-2xl border border-violet-200 bg-white/80 p-4 dark:border-violet-900/70 dark:bg-slate-950/70"
    data-testid="cockpit-quick-generate-llm-panel"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <Sparkles class="size-4 text-violet-600" aria-hidden="true" />
          <h3 class="text-lg font-semibold text-slate-950 dark:text-slate-50">
            Describe a Pay Code
          </h3>
        </div>
        <p
          class="mt-1 max-w-2xl text-sm leading-5 text-slate-600 dark:text-slate-300"
        >
          Describe the amount and claim requirements in plain language. We will
          update your Order and stamp as the instructions become ready. Review
          the cost before issuing.
        </p>
      </div>
      <CockpitQuickGenerateSurfaceSwitch
        :model-value="issuanceSurface"
        :surfaces="availableSurfaces"
        class="w-auto shrink-0 p-0.5 [&>button]:min-h-7 [&>button]:px-2"
        @update:model-value="emit('update:issuanceSurface', $event)"
      />
    </div>
    <CockpitQuickGeneratePromptComposer
      review-only
      :stamp-preview="stampPreview"
      :endpoint="promptComposerEndpoint"
      @interpreted="emit('interpreted', $event)"
      @activate="emit('activate', $event)"
      @show-cost="emit('show-cost')"
      @show-stamp="emit('show-stamp')"
    />
  </section>
</template>
