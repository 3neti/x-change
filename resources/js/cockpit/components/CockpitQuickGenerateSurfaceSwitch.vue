<script setup lang="ts">
import { QrCode, Sparkles, WandSparkles } from "lucide-vue-next";
import { computed } from "vue";

export type CockpitQuickGenerateSurface = "composer" | "llm" | "pos" | "qr";

const props = withDefaults(
  defineProps<{
    modelValue: CockpitQuickGenerateSurface;
    disabled?: boolean;
    surfaces?: CockpitQuickGenerateSurface[];
  }>(),
  {
    disabled: false,
    surfaces: () => ["composer", "llm", "pos", "qr"],
  },
);

const surfaces = computed(() => props.surfaces);
const columnsClass = computed(() => {
  if (surfaces.value.length <= 2) {
    return "grid-cols-2";
  }

  if (surfaces.value.length === 3) {
    return "grid-cols-3";
  }

  return "grid-cols-4";
});

const emit = defineEmits<{
  "update:modelValue": [value: CockpitQuickGenerateSurface];
}>();
</script>

<template>
  <div
    class="inline-grid min-w-0 rounded-full bg-slate-100 p-1 dark:bg-slate-900"
    :class="columnsClass"
    role="group"
    aria-label="Issuance workspace"
    data-testid="cockpit-quick-generate-surface-toggle"
  >
    <button
      v-if="surfaces.includes('composer')"
      type="button"
      :aria-pressed="modelValue === 'composer'"
      :class="[
        'inline-flex min-h-8 min-w-0 items-center justify-center gap-1.5 rounded-full px-2.5 text-xs font-semibold transition sm:px-3',
        modelValue === 'composer'
          ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-800 dark:text-slate-50'
          : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white',
      ]"
      :disabled="disabled"
      data-testid="cockpit-quick-generate-surface-composer"
      @click="emit('update:modelValue', 'composer')"
    >
      <WandSparkles class="size-3.5 shrink-0" aria-hidden="true" />
      <span class="truncate">Composer</span>
    </button>
    <button
      v-if="surfaces.includes('llm')"
      type="button"
      :aria-pressed="modelValue === 'llm'"
      :class="[
        'inline-flex min-h-8 min-w-0 items-center justify-center gap-1.5 rounded-full px-2.5 text-xs font-semibold transition sm:px-3',
        modelValue === 'llm'
          ? 'bg-white text-violet-800 shadow-sm dark:bg-slate-800 dark:text-violet-200'
          : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white',
      ]"
      :disabled="disabled"
      data-testid="cockpit-quick-generate-surface-llm"
      @click="emit('update:modelValue', 'llm')"
    >
      <Sparkles class="size-3.5 shrink-0" aria-hidden="true" />
      <span>LLM</span>
    </button>
    <button
      v-if="surfaces.includes('pos')"
      type="button"
      :aria-pressed="modelValue === 'pos'"
      :class="[
        'inline-flex min-h-8 min-w-0 items-center justify-center gap-1.5 rounded-full px-2.5 text-xs font-semibold transition sm:px-3',
        modelValue === 'pos'
          ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
          : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white',
      ]"
      :disabled="disabled"
      data-testid="cockpit-quick-generate-surface-pos"
      @click="emit('update:modelValue', 'pos')"
    >
      <QrCode class="size-3.5 shrink-0" aria-hidden="true" />
      <span>POS</span>
    </button>
    <button
      v-if="surfaces.includes('qr')"
      type="button"
      :aria-pressed="modelValue === 'qr'"
      :class="[
        'inline-flex min-h-8 min-w-0 items-center justify-center gap-1.5 rounded-full px-2.5 text-xs font-semibold transition sm:px-3',
        modelValue === 'qr'
          ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
          : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white',
      ]"
      :disabled="disabled"
      data-testid="cockpit-quick-generate-surface-qr"
      @click="emit('update:modelValue', 'qr')"
    >
      <QrCode class="size-3.5 shrink-0" aria-hidden="true" />
      <span class="whitespace-nowrap">QR Code</span>
    </button>
  </div>
</template>
