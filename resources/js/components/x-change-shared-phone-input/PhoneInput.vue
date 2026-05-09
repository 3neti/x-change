<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: string;
    error?: string;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    placeholder?: string;
    autofocus?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    error: '',
    disabled: false,
    readonly: false,
    required: false,
    placeholder: '9XXXXXXXXX',
    autofocus: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const displayValue = ref('');
const isFocused = ref(false);

/** Extract up to 10 subscriber digits from any phone format. */
function extractDigits(value: string): string {
    if (!value) return '';
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('63') && digits.length > 10) digits = digits.substring(2);
    if (digits.startsWith('0') && digits.length >= 11) digits = digits.substring(1);
    return digits.substring(0, 10);
}

/** Format subscriber digits as (XXX) XXX-XXXX */
function formatDisplay(digits: string): string {
    if (!digits) return '';
    if (digits.length <= 3) return `(${digits})`;
    if (digits.length <= 6) return `(${digits.substring(0, 3)}) ${digits.substring(3)}`;
    return `(${digits.substring(0, 3)}) ${digits.substring(3, 6)}-${digits.substring(6)}`;
}

function handleInput(event: Event) {
    const input = event.target as HTMLInputElement;
    // While typing, just let the user type freely — no reformatting
    const digits = extractDigits(input.value);
    emit('update:modelValue', digits.length ? `+63${digits}` : '');
}

function handleFocus() {
    isFocused.value = true;
    // Switch to raw digits for easy editing
    const digits = extractDigits(props.modelValue);
    displayValue.value = digits;
}

function handleBlur() {
    isFocused.value = false;
    // Clean up and format on exit
    const digits = extractDigits(props.modelValue);
    displayValue.value = formatDisplay(digits);
    // Ensure emitted value is clean E.164
    emit('update:modelValue', digits.length ? `+63${digits}` : '');
}

// Sync display from external changes (page load, profile data)
watch(() => props.modelValue, (val) => {
    if (isFocused.value) return;
    displayValue.value = formatDisplay(extractDigits(val ?? ''));
}, { immediate: true });

onMounted(() => {
    if (props.autofocus && inputRef.value) inputRef.value.focus();
});
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <div
            class="flex items-center rounded-md border border-input shadow-xs"
            :class="[
                disabled ? 'opacity-50' : '',
                error ? 'border-red-500' : 'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
            ]"
        >
            <span class="inline-flex items-center gap-1.5 pl-3 pr-2 text-sm text-muted-foreground select-none border-r border-input">
                🇵🇭 +63
            </span>
            <input
                ref="inputRef"
                type="tel"
                v-model="displayValue"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :placeholder="placeholder"
                class="flex-1 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                @input="handleInput"
                @focus="handleFocus"
                @blur="handleBlur"
            />
        </div>
        <p v-if="error" class="text-sm text-red-600 mt-1">{{ error }}</p>
    </div>
</template>

