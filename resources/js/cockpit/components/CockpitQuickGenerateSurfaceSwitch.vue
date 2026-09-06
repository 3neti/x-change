<script setup lang="ts">
import { QrCode, WandSparkles } from "lucide-vue-next";

export type CockpitQuickGenerateSurface = "composer" | "pos";

withDefaults(
  defineProps<{
    modelValue: CockpitQuickGenerateSurface;
    disabled?: boolean;
  }>(),
  {
    disabled: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: CockpitQuickGenerateSurface];
}>();
</script>

<template>
  <div
    class="inline-grid min-w-0 grid-cols-2 rounded-full bg-slate-100 p-1 dark:bg-slate-900"
    role="group"
    aria-label="Issuance workspace"
    data-testid="cockpit-quick-generate-surface-toggle"
  >
    <button
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
  </div>
</template>
