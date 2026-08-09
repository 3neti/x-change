<script setup lang="ts">
import NumericKeypad from "@/components/NumericKeypad.vue";
import { Calculator } from "lucide-vue-next";
import { computed, ref } from "vue";

const props = withDefaults(
  defineProps<{
    modelValue: string;
    disabled?: boolean;
    quickAmounts?: number[];
  }>(),
  {
    disabled: false,
    quickAmounts: () => [100, 500, 1000, 2000, 5000, 10000],
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: string];
}>();

const inputElement = ref<HTMLInputElement | null>(null);
const keypadOpen = ref(false);

const numericValue = computed<number | null>(() => {
  const value = Number.parseFloat(props.modelValue);

  return Number.isFinite(value) && value > 0 ? value : null;
});

const displayValue = computed(() => {
  if (numericValue.value === null) {
    return "";
  }

  return numericValue.value.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
});

function openKeypad(): void {
  if (!props.disabled) {
    keypadOpen.value = true;
  }
}

function confirmAmount(value: number): void {
  emit("update:modelValue", value.toFixed(2));
}

function focus(): void {
  inputElement.value?.focus();
  inputElement.value?.select();
}

defineExpose({ focus });
</script>

<template>
  <div class="relative">
    <span
      class="pointer-events-none absolute inset-y-0 left-0 z-10 inline-flex items-center pl-3 text-sm font-semibold text-slate-500 dark:text-slate-400"
      aria-hidden="true"
    >
      ₱
    </span>
    <input
      id="cockpit-quick-generate-primary-amount"
      ref="inputElement"
      type="text"
      inputmode="decimal"
      readonly
      :value="displayValue"
      :disabled="disabled"
      placeholder="0.00"
      class="h-12 w-full cursor-pointer rounded-xl border border-slate-200 bg-white pr-11 pl-8 text-base font-semibold tabular-nums text-slate-950 shadow-sm transition hover:border-emerald-300 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-emerald-700"
      data-testid="cockpit-quick-generate-primary-amount"
      aria-haspopup="dialog"
      @click="openKeypad"
      @keydown.enter.prevent="openKeypad"
      @keydown.space.prevent="openKeypad"
    />
    <Calculator
      class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400"
      aria-hidden="true"
    />

    <NumericKeypad
      v-model:open="keypadOpen"
      :model-value="numericValue"
      mode="amount"
      :min="0.01"
      :quick-amounts="quickAmounts"
      allow-decimal
      title="Pay Code Amount"
      @confirm="confirmAmount"
    />
  </div>
</template>
