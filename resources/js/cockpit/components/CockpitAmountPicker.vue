<script setup lang="ts">
import NumericKeypad from "@/components/NumericKeypad.vue";
import { Calculator } from "lucide-vue-next";
import { computed, ref, watch } from "vue";

const props = withDefaults(
  defineProps<{
    modelValue: string;
    disabled?: boolean;
    quickAmounts?: number[];
    estimatedCost?: string | null;
    estimatePending?: boolean;
    estimateAffordability?:
      "unknown" | "affordable" | "insufficient-client-funds";
  }>(),
  {
    disabled: false,
    quickAmounts: () => [100, 500, 1000, 2000, 5000, 10000],
    estimatedCost: null,
    estimatePending: false,
    estimateAffordability: "unknown",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: string];
  preview: [value: number | null];
}>();

const inputElement = ref<HTMLInputElement | null>(null);
const keypadOpen = ref(false);
const keypadInitialEntry = ref<string | null>(null);

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
    keypadInitialEntry.value = null;
    keypadOpen.value = true;
  }
}

function openKeypadFromNumericKey(event: KeyboardEvent): void {
  if (
    props.disabled ||
    event.altKey ||
    event.ctrlKey ||
    event.metaKey ||
    event.shiftKey
  ) {
    return;
  }

  const isDigit = event.key >= "0" && event.key <= "9";
  const isDecimal = event.key === "." || event.key === ",";

  if (!isDigit && !isDecimal) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  keypadInitialEntry.value = isDecimal ? "0." : event.key;
  keypadOpen.value = true;
}

function confirmAmount(value: number): void {
  emit("update:modelValue", value.toFixed(2));
}

function previewAmount(value: number): void {
  emit("preview", value);
}

function synchronizeProgrammaticInput(event: Event): void {
  emit("update:modelValue", (event.target as HTMLInputElement).value);
}

function focus(): void {
  inputElement.value?.focus();
  inputElement.value?.select();
}

watch(keypadOpen, (isOpen) => {
  if (!isOpen) {
    keypadInitialEntry.value = null;
    emit("preview", null);
  }
});

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
      class="h-12 w-full cursor-pointer rounded-xl border border-slate-200 bg-white pr-11 pl-8 text-base font-semibold tabular-nums text-slate-950 shadow-sm transition hover:border-emerald-300 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-emerald-700"
      data-testid="cockpit-quick-generate-primary-amount"
      aria-haspopup="dialog"
      aria-label="Pay Code amount. Type a number or open the calculator."
      @input="synchronizeProgrammaticInput"
      @click="openKeypad"
      @keydown="openKeypadFromNumericKey"
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
      :initial-entry="keypadInitialEntry"
      appearance="cockpit"
      allow-decimal
      title="Pay Code Amount"
      @confirm="confirmAmount"
      @preview="previewAmount"
    >
      <template #summary>
        <div
          class="flex min-h-5 items-baseline justify-between gap-3 text-xs leading-5"
          data-testid="cockpit-amount-picker-estimated-cost"
          :data-affordability="estimateAffordability"
          aria-live="polite"
        >
          <span
            :class="
              estimateAffordability === 'insufficient-client-funds'
                ? 'font-semibold text-rose-600 dark:text-rose-300'
                : 'font-medium text-slate-500 dark:text-slate-400'
            "
          >
            Estimated Cost
          </span>
          <span
            v-if="estimatedCost !== null && !estimatePending"
            class="shrink-0 font-semibold tabular-nums"
            :class="
              estimateAffordability === 'insufficient-client-funds'
                ? 'text-rose-600 dark:text-rose-300'
                : 'text-slate-700 dark:text-slate-200'
            "
            data-testid="cockpit-amount-picker-estimated-cost-value"
          >
            {{ estimatedCost }}
          </span>
          <span
            v-else-if="estimatePending"
            class="shrink-0 text-slate-400 dark:text-slate-500"
            data-testid="cockpit-amount-picker-estimated-cost-loading"
          >
            Calculating…
          </span>
          <span
            v-else
            class="shrink-0 text-slate-400 dark:text-slate-500"
            data-testid="cockpit-amount-picker-estimated-cost-unavailable"
          >
            —
          </span>
        </div>
      </template>
    </NumericKeypad>
  </div>
</template>
