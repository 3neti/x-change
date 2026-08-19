<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Delete, Check } from "lucide-vue-next";

type KeypadMode = "amount" | "count";
type KeypadAppearance = "default" | "cockpit";

interface Props {
  modelValue?: number | null;
  mode: KeypadMode;
  min?: number;
  max?: number;
  open: boolean;
  allowDecimal?: boolean;
  title?: string;
  hideCurrency?: boolean;
  quickAmounts?: number[];
  initialEntry?: string | null;
  appearance?: KeypadAppearance;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  min: 1,
  allowDecimal: false,
  quickAmounts: () => [],
  initialEntry: null,
  appearance: "default",
});

const emit = defineEmits<{
  "update:open": [value: boolean];
  confirm: [value: number];
  preview: [value: number];
}>();

const digits = ref<string>("");
const isCockpit = computed(() => props.appearance === "cockpit");

function normalizedInitialEntry(): string | null {
  if (props.initialEntry === null) {
    return null;
  }

  const candidate = props.initialEntry.replace(",", ".");

  if (!/^\d*(?:\.\d{0,2})?$/.test(candidate)) {
    return null;
  }

  return candidate;
}

watch(
  () => props.open,
  (isOpen) => {
    const initialEntry = normalizedInitialEntry();

    if (isOpen && initialEntry !== null) {
      digits.value = initialEntry;
    } else if (isOpen && props.modelValue) {
      digits.value = props.modelValue.toString();
    } else if (isOpen) {
      digits.value = "";
    }
  },
);

// Computed current value
const currentValue = computed(() => {
  if (!digits.value) return 0;
  const num = props.allowDecimal
    ? parseFloat(digits.value)
    : parseInt(digits.value);
  return isNaN(num) ? 0 : num;
});

watch(
  [() => props.open, currentValue],
  ([isOpen, value]) => {
    if (isOpen) {
      emit("preview", value);
    }
  },
  { immediate: true },
);

// Formatted display
const displayValue = computed(() => {
  if (!digits.value) {
    if (props.hideCurrency) return "0";
    return props.mode === "amount" ? "₱0" : "0";
  }

  // Show raw input while typing (preserves decimal point)
  const displayNum = digits.value;

  if (props.mode === "amount") {
    // For amounts, format with proper decimal places
    const num = currentValue.value;
    if (props.allowDecimal && digits.value.includes(".")) {
      return props.hideCurrency ? displayNum : `₱${displayNum}`;
    }
    return props.hideCurrency
      ? num.toLocaleString()
      : `₱${num.toLocaleString()}`;
  }

  // Count mode - show raw number only (title provides context)
  const num = currentValue.value;
  // Preserve decimal point during typing
  if (props.allowDecimal && digits.value.includes(".")) {
    return displayNum;
  }
  return `${num}`;
});

// Title based on mode
const displayTitle = computed(() => {
  // Use custom title if provided
  if (props.title) return props.title;

  // Fall back to mode-based title
  return props.mode === "amount" ? "Enter Amount" : "Enter Quantity";
});

// Description based on mode
const description = computed(() => {
  if (props.mode === "amount") {
    if (props.hideCurrency) {
      return `Minimum: ${props.min}`;
    }
    return `Minimum: ₱${props.min.toLocaleString()}`;
  }
  return `Minimum: ${props.min}`;
});

// Can confirm (value meets minimum)
const canConfirm = computed(() => {
  return (
    currentValue.value >= props.min &&
    (!props.max || currentValue.value <= props.max)
  );
});

// Handle digit press
const pressDigit = (digit: number) => {
  // Prevent leading zeros (unless decimal point exists)
  if (digits.value === "" && digit === 0) return;

  if (
    props.allowDecimal &&
    digits.value.includes(".") &&
    digits.value.split(".")[1].length >= 2
  )
    return;

  // Append digit
  digits.value += digit.toString();

  // Haptic feedback if supported
  if ("vibrate" in navigator) {
    navigator.vibrate(10);
  }
};

const selectQuickAmount = (amount: number) => {
  if (amount < props.min || (props.max !== undefined && amount > props.max))
    return;

  digits.value = amount.toString();
};

// Handle decimal point press
const pressDecimal = () => {
  // Only allow if decimals are enabled
  if (!props.allowDecimal) return;

  // Prevent multiple decimal points
  if (digits.value.includes(".")) return;

  // If empty, prepend zero
  if (digits.value === "") {
    digits.value = "0.";
  } else {
    digits.value += ".";
  }

  // Haptic feedback if supported
  if ("vibrate" in navigator) {
    navigator.vibrate(10);
  }
};

// Handle backspace
const pressBackspace = () => {
  digits.value = digits.value.slice(0, -1);

  if ("vibrate" in navigator) {
    navigator.vibrate(10);
  }
};

// Handle confirm
const confirm = () => {
  if (!canConfirm.value) return;

  emit("confirm", currentValue.value);
  emit("update:open", false);
};

// Handle cancel
const cancel = () => {
  emit("update:open", false);
};

// Keyboard support
const handleKeyDown = (event: KeyboardEvent) => {
  if (!props.open) return;

  // Numeric keys
  if (event.key >= "0" && event.key <= "9") {
    event.preventDefault();
    pressDigit(parseInt(event.key));
  }
  // Decimal point
  else if ((event.key === "." || event.key === ",") && props.allowDecimal) {
    event.preventDefault();
    pressDecimal();
  }
  // Backspace
  else if (event.key === "Backspace") {
    event.preventDefault();
    pressBackspace();
  }
  // Enter
  else if (event.key === "Enter") {
    event.preventDefault();
    if (canConfirm.value) {
      confirm();
    }
  }
  // Escape
  else if (event.key === "Escape") {
    event.preventDefault();
    cancel();
  }
};

// Attach keyboard listener when open
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener("keydown", handleKeyDown);
    } else {
      window.removeEventListener("keydown", handleKeyDown);
    }
  },
);

onBeforeUnmount(() => {
  window.removeEventListener("keydown", handleKeyDown);
});
</script>

<template>
  <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
    <DialogContent
      :data-appearance="appearance"
      data-testid="numeric-keypad-dialog"
      :class="[
        'bottom-0! top-auto! max-h-[92dvh] translate-y-0! overflow-y-auto rounded-b-none sm:bottom-auto! sm:top-1/2! sm:-translate-y-1/2!',
        isCockpit
          ? 'gap-4 rounded-t-3xl border-emerald-200 bg-white p-4 shadow-2xl shadow-slate-950/20 sm:max-w-sm sm:rounded-2xl sm:p-5 dark:border-emerald-900/70 dark:bg-slate-950'
          : 'sm:max-w-md sm:rounded-lg',
      ]"
    >
      <DialogHeader :class="isCockpit ? 'gap-1 pr-8 text-left' : ''">
        <DialogTitle
          :class="
            isCockpit
              ? 'text-base font-semibold text-slate-950 dark:text-slate-50'
              : ''
          "
        >
          {{ displayTitle }}
        </DialogTitle>
        <DialogDescription
          :class="isCockpit ? 'text-xs text-slate-500 dark:text-slate-400' : ''"
        >
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <div
        :class="[
          'flex items-center justify-center',
          isCockpit
            ? 'min-h-24 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-5 dark:border-emerald-900/60 dark:bg-emerald-950/25'
            : 'py-6',
        ]"
        data-testid="numeric-keypad-display"
      >
        <div
          :class="[
            'text-4xl font-bold tracking-tight tabular-nums',
            isCockpit && canConfirm
              ? 'text-slate-950 dark:text-slate-50'
              : isCockpit
                ? 'text-slate-400 dark:text-slate-600'
                : canConfirm
                  ? 'text-foreground'
                  : 'text-muted-foreground',
          ]"
        >
          {{ displayValue }}
        </div>
      </div>

      <div
        v-if="$slots.summary"
        class="px-1"
        data-testid="numeric-keypad-summary"
      >
        <slot name="summary" :value="currentValue" />
      </div>

      <div
        v-if="quickAmounts.length > 0"
        class="grid grid-cols-3 gap-2"
        data-testid="numeric-keypad-quick-amounts"
      >
        <Button
          v-for="quickAmount in quickAmounts"
          :key="quickAmount"
          type="button"
          variant="outline"
          :class="[
            'h-10 rounded-xl text-sm font-semibold tabular-nums transition',
            isCockpit && currentValue === quickAmount
              ? 'border-emerald-400 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100 dark:ring-emerald-900'
              : isCockpit
                ? 'border-slate-200 bg-white text-slate-700 shadow-none hover:border-emerald-300 hover:bg-emerald-50/60 hover:text-slate-950 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/30 dark:hover:text-slate-50'
                : '',
          ]"
          :data-testid="`numeric-keypad-quick-${quickAmount}`"
          @click="selectQuickAmount(quickAmount)"
        >
          {{
            hideCurrency
              ? quickAmount.toLocaleString()
              : `₱${quickAmount.toLocaleString()}`
          }}
        </Button>
      </div>

      <div class="space-y-2">
        <div class="grid grid-cols-3 gap-2">
          <Button
            v-for="digit in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
            :key="`digit-${digit}`"
            type="button"
            @click="pressDigit(digit)"
            variant="outline"
            size="lg"
            :class="[
              'h-14 rounded-xl text-xl font-semibold tabular-nums',
              isCockpit
                ? 'border-slate-200 bg-white text-slate-950 shadow-none hover:border-emerald-300 hover:bg-emerald-50 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/35'
                : '',
            ]"
          >
            {{ digit }}
          </Button>

          <Button
            type="button"
            @click="pressBackspace"
            variant="outline"
            size="lg"
            :class="[
              'h-14 rounded-xl',
              isCockpit
                ? 'border-slate-200 bg-slate-50 text-slate-600 shadow-none hover:border-slate-300 hover:bg-slate-100 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                : '',
            ]"
            :disabled="!digits"
            aria-label="Delete last digit"
          >
            <Delete class="h-5 w-5" />
          </Button>

          <Button
            type="button"
            @click="pressDigit(0)"
            variant="outline"
            size="lg"
            :class="[
              'h-14 rounded-xl text-xl font-semibold tabular-nums',
              isCockpit
                ? 'border-slate-200 bg-white text-slate-950 shadow-none hover:border-emerald-300 hover:bg-emerald-50 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/35'
                : '',
            ]"
          >
            0
          </Button>

          <Button
            v-if="allowDecimal"
            type="button"
            @click="pressDecimal"
            variant="outline"
            size="lg"
            :class="[
              'h-14 rounded-xl text-xl font-semibold',
              isCockpit
                ? 'border-slate-200 bg-white text-slate-950 shadow-none hover:border-emerald-300 hover:bg-emerald-50 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/35'
                : '',
            ]"
            :disabled="digits.includes('.')"
          >
            .
          </Button>

          <Button
            v-else
            type="button"
            @click="confirm"
            variant="default"
            size="lg"
            :class="[
              'h-14 rounded-xl',
              isCockpit
                ? 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:border-emerald-700 focus-visible:ring-emerald-500/30 dark:bg-emerald-600 dark:hover:bg-emerald-500'
                : '',
            ]"
            :disabled="!canConfirm"
            aria-label="Use value"
          >
            <Check class="h-5 w-5" />
          </Button>
        </div>

        <Button
          v-if="allowDecimal"
          type="button"
          @click="confirm"
          variant="default"
          size="lg"
          :class="[
            'min-h-14 w-full rounded-xl py-4 font-semibold',
            isCockpit
              ? 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:border-emerald-700 focus-visible:ring-emerald-500/30 dark:bg-emerald-600 dark:hover:bg-emerald-500'
              : '',
          ]"
          :disabled="!canConfirm"
          data-testid="numeric-keypad-confirm"
        >
          <Check class="mr-2 h-5 w-5" />
          Use Amount
        </Button>
      </div>

      <DialogFooter class="sm:justify-center">
        <Button
          type="button"
          variant="ghost"
          @click="cancel"
          :class="[
            'min-h-14 w-full rounded-xl py-4',
            isCockpit
              ? 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:ring-emerald-500/20 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
              : '',
          ]"
          data-testid="numeric-keypad-cancel"
        >
          Cancel
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
