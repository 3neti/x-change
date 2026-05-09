<script setup lang="ts">
import { ref, computed } from 'vue';
import { NumberInput } from '@/components/ui/number-input';
import NumericKeypad from '@/components/NumericKeypad.vue';
import { Pencil } from 'lucide-vue-next';

interface Props {
  modelValue?: number | null;
  defaultValue?: number;
  min?: number;
  max?: number;
  step?: number | string;
  placeholder?: string;
  disabled?: boolean;
  prefix?: string;
  suffix?: string;
  allowDecimal?: boolean;
  keypadMode?: 'amount' | 'count';
  keypadTitle?: string;
  hero?: boolean;
  heroLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  min: 0,
  step: 1,
  allowDecimal: false,
  keypadMode: 'amount',
  hero: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const showKeypad = ref(false);

// Format value for display - showing 2 decimals for decimal inputs
const displayValue = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return '';
  const num = typeof props.modelValue === 'number' ? props.modelValue : parseFloat(String(props.modelValue));
  if (isNaN(num)) return '';
  return props.allowDecimal ? num.toFixed(2) : num.toString();
});

// Hero mode: formatted with currency prefix and locale grouping
const heroDisplay = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
    return `${props.prefix ?? ''}0.00`;
  }
  const num = typeof props.modelValue === 'number' ? props.modelValue : parseFloat(String(props.modelValue));
  if (isNaN(num)) return `${props.prefix ?? ''}0.00`;
  return `${props.prefix ?? ''}${num.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
});

// Determine keypad mode based on props
const effectiveKeypadMode = computed(() => {
  // Percentage should use amount mode (no "vouchers" text)
  return props.keypadMode;
});

// Determine keypad title based on context
const effectiveKeypadTitle = computed(() => {
  // Use custom title if provided
  if (props.keypadTitle) return props.keypadTitle;
  
  // Fall back to context-based title
  if (props.suffix === '%') return 'Enter Percentage';
  if (props.prefix === '₱') return 'Enter Amount';
  return 'Enter Value';
});

// Open keypad on click
const handleClick = () => {
  if (props.disabled) return;
  showKeypad.value = true;
};

// Handle keypad confirm
const handleConfirm = (value: number) => {
  emit('update:modelValue', value);
  showKeypad.value = false;
};
</script>

<template>
  <div>
    <!-- Hero variant: compact centered tappable display -->
    <div
      v-if="hero"
      class="group text-center py-3 cursor-pointer"
      :class="{ 'pointer-events-none opacity-50': disabled }"
      @click="handleClick"
    >
      <p v-if="heroLabel" class="text-xs text-muted-foreground mb-1.5 uppercase tracking-wider">{{ heroLabel }}</p>
      <div class="inline-flex items-center gap-1.5">
        <span
          class="text-3xl font-bold tabular-nums transition-colors border-b-2 border-dashed border-primary/25 group-hover:border-primary/50 pb-0.5"
          :class="modelValue ? 'text-foreground group-hover:text-primary' : 'text-muted-foreground/40 group-hover:text-muted-foreground'"
        >
          {{ heroDisplay }}
        </span>
        <Pencil class="h-3 w-3 text-muted-foreground/30 group-hover:text-primary/60 transition-colors shrink-0" />
      </div>
    </div>

    <!-- Default variant: input-like display -->
    <div v-else class="relative">
      <!-- Prefix -->
      <span
        v-if="prefix"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-base md:text-sm z-10"
      >
        {{ prefix }}
      </span>
      
      <!-- Formatted Display Input -->
      <input
        type="text"
        :value="displayValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="[
          'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
          'cursor-pointer hover:bg-accent',
          prefix && 'pl-8',
          suffix && 'pr-8',
        ]"
        readonly
        @click="handleClick"
      >
      
      <!-- Suffix -->
      <span
        v-if="suffix"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground text-base md:text-sm z-10"
      >
        {{ suffix }}
      </span>
    </div>
    
    <NumericKeypad
      :open="showKeypad"
      @update:open="(val) => showKeypad = val"
      @confirm="handleConfirm"
      :model-value="modelValue"
      :mode="effectiveKeypadMode"
      :min="min"
      :max="max"
      :allow-decimal="allowDecimal"
      :title="effectiveKeypadTitle"
      :hide-currency="suffix === '%'"
    />
  </div>
</template>
