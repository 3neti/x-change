<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Delete, Check } from 'lucide-vue-next';

type KeypadMode = 'amount' | 'count';

interface Props {
  modelValue?: number | null;
  mode: KeypadMode;
  min?: number;
  max?: number;
  open: boolean;
  allowDecimal?: boolean;
  title?: string;
  hideCurrency?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  min: 1,
  allowDecimal: false,
});

const emit = defineEmits<{
  'update:open': [value: boolean];
  'confirm': [value: number];
}>();

// Internal digit accumulator
const digits = ref<string>('');

// Initialize digits from modelValue when dialog opens
watch(() => props.open, (isOpen) => {
  if (isOpen && props.modelValue) {
    digits.value = props.modelValue.toString();
  } else if (isOpen) {
    digits.value = '';
  }
});

// Computed current value
const currentValue = computed(() => {
  if (!digits.value) return 0;
  const num = props.allowDecimal ? parseFloat(digits.value) : parseInt(digits.value);
  return isNaN(num) ? 0 : num;
});

// Formatted display
const displayValue = computed(() => {
  if (!digits.value) {
    if (props.hideCurrency) return '0';
    return props.mode === 'amount' ? '₱0' : '0';
  }
  
  // Show raw input while typing (preserves decimal point)
  const displayNum = digits.value;
  
  if (props.mode === 'amount') {
    // For amounts, format with proper decimal places
    const num = currentValue.value;
    if (props.allowDecimal && digits.value.includes('.')) {
      return props.hideCurrency ? displayNum : `₱${displayNum}`;
    }
    return props.hideCurrency ? num.toLocaleString() : `₱${num.toLocaleString()}`;
  }
  
  // Count mode - show raw number only (title provides context)
  const num = currentValue.value;
  // Preserve decimal point during typing
  if (props.allowDecimal && digits.value.includes('.')) {
    return displayNum;
  }
  return `${num}`;
});

// Title based on mode
const displayTitle = computed(() => {
  // Use custom title if provided
  if (props.title) return props.title;
  
  // Fall back to mode-based title
  return props.mode === 'amount' ? 'Enter Amount' : 'Enter Quantity';
});

// Description based on mode
const description = computed(() => {
  if (props.mode === 'amount') {
    if (props.hideCurrency) {
      return `Minimum: ${props.min}`;
    }
    return `Minimum: ₱${props.min.toLocaleString()}`;
  }
  return `Minimum: ${props.min}`;
});

// Can confirm (value meets minimum)
const canConfirm = computed(() => {
  return currentValue.value >= props.min && 
         (!props.max || currentValue.value <= props.max);
});

// Handle digit press
const pressDigit = (digit: number) => {
  // Prevent leading zeros (unless decimal point exists)
  if (digits.value === '' && digit === 0) return;
  
  // Append digit
  digits.value += digit.toString();
  
  // Haptic feedback if supported
  if ('vibrate' in navigator) {
    navigator.vibrate(10);
  }
};

// Handle decimal point press
const pressDecimal = () => {
  // Only allow if decimals are enabled
  if (!props.allowDecimal) return;
  
  // Prevent multiple decimal points
  if (digits.value.includes('.')) return;
  
  // If empty, prepend zero
  if (digits.value === '') {
    digits.value = '0.';
  } else {
    digits.value += '.';
  }
  
  // Haptic feedback if supported
  if ('vibrate' in navigator) {
    navigator.vibrate(10);
  }
};

// Handle backspace
const pressBackspace = () => {
  digits.value = digits.value.slice(0, -1);
  
  if ('vibrate' in navigator) {
    navigator.vibrate(10);
  }
};

// Handle confirm
const confirm = () => {
  if (!canConfirm.value) return;
  
  emit('confirm', currentValue.value);
  emit('update:open', false);
};

// Handle cancel
const cancel = () => {
  emit('update:open', false);
};

// Keyboard support
const handleKeyDown = (event: KeyboardEvent) => {
  if (!props.open) return;
  
  // Numeric keys
  if (event.key >= '0' && event.key <= '9') {
    event.preventDefault();
    pressDigit(parseInt(event.key));
  }
  // Decimal point
  else if ((event.key === '.' || event.key === ',') && props.allowDecimal) {
    event.preventDefault();
    pressDecimal();
  }
  // Backspace
  else if (event.key === 'Backspace') {
    event.preventDefault();
    pressBackspace();
  }
  // Enter
  else if (event.key === 'Enter') {
    event.preventDefault();
    if (canConfirm.value) {
      confirm();
    }
  }
  // Escape
  else if (event.key === 'Escape') {
    event.preventDefault();
    cancel();
  }
};

// Attach keyboard listener when open
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    window.addEventListener('keydown', handleKeyDown);
  } else {
    window.removeEventListener('keydown', handleKeyDown);
  }
});
</script>

<template>
  <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ displayTitle }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>
      
      <!-- Display -->
      <div class="flex items-center justify-center py-6">
        <div 
          class="text-4xl font-bold tracking-tight"
          :class="canConfirm ? 'text-foreground' : 'text-muted-foreground'"
        >
          {{ displayValue }}
        </div>
      </div>
      
      <!-- Keypad Grid -->
      <div class="space-y-2">
        <div class="grid grid-cols-3 gap-2">
          <!-- Row 1: 1, 2, 3 -->
          <Button
            v-for="digit in [1, 2, 3]"
            :key="`digit-${digit}`"
            @click="pressDigit(digit)"
            variant="outline"
            size="lg"
            class="h-14 text-xl font-semibold"
          >
            {{ digit }}
          </Button>
          
          <!-- Row 2: 4, 5, 6 -->
          <Button
            v-for="digit in [4, 5, 6]"
            :key="`digit-${digit}`"
            @click="pressDigit(digit)"
            variant="outline"
            size="lg"
            class="h-14 text-xl font-semibold"
          >
            {{ digit }}
          </Button>
          
          <!-- Row 3: 7, 8, 9 -->
          <Button
            v-for="digit in [7, 8, 9]"
            :key="`digit-${digit}`"
            @click="pressDigit(digit)"
            variant="outline"
            size="lg"
            class="h-14 text-xl font-semibold"
          >
            {{ digit }}
          </Button>
          
          <!-- Row 4: Backspace, 0, Decimal/Confirm -->
          <Button
            @click="pressBackspace"
            variant="outline"
            size="lg"
            class="h-14"
            :disabled="!digits"
          >
            <Delete class="h-5 w-5" />
          </Button>
          
          <Button
            @click="pressDigit(0)"
            variant="outline"
            size="lg"
            class="h-14 text-xl font-semibold"
          >
            0
          </Button>
          
          <!-- Decimal point button (only if allowDecimal) -->
          <Button
            v-if="allowDecimal"
            @click="pressDecimal"
            variant="outline"
            size="lg"
            class="h-14 text-xl font-semibold"
            :disabled="digits.includes('.')"
          >
            .
          </Button>
          
          <!-- Confirm button (when no decimal) -->
          <Button
            v-else
            @click="confirm"
            variant="default"
            size="lg"
            class="h-14"
            :disabled="!canConfirm"
          >
            <Check class="h-5 w-5" />
          </Button>
        </div>
        
        <!-- Full-width confirm button (when decimal enabled) -->
        <Button
          v-if="allowDecimal"
          @click="confirm"
          variant="default"
          size="lg"
          class="h-14 w-full"
          :disabled="!canConfirm"
        >
          <Check class="h-5 w-5 mr-2" />
          Confirm
        </Button>
      </div>
      
      <DialogFooter class="sm:justify-center">
        <Button variant="ghost" @click="cancel" class="w-full">
          Cancel
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
