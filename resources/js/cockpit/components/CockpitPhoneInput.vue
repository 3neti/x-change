<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        error?: string | null;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        placeholder?: string;
        autofocus?: boolean;
        inputTestId?: string;
    }>(),
    {
        modelValue: '',
        error: null,
        disabled: false,
        readonly: false,
        required: false,
        placeholder: '9173011987',
        autofocus: false,
        inputTestId: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const displayValue = ref('');
const isFocused = ref(false);

function extractSubscriberDigits(value: string): string {
    let digits = value.replace(/\D/g, '');

    if (digits.startsWith('63') && digits.length > 10) {
        digits = digits.slice(2);
    }

    if (digits.startsWith('0') && digits.length >= 11) {
        digits = digits.slice(1);
    }

    if (digits.length > 10) {
        digits = digits.slice(-10);
    }

    return digits.slice(0, 10);
}

function formatDisplay(digits: string): string {
    if (digits === '') {
        return '';
    }

    if (digits.length <= 3) {
        return `(${digits}`;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    }

    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
}

function emitNormalized(value: string): void {
    const digits = extractSubscriberDigits(value);

    emit('update:modelValue', digits.length === 10 ? `+63${digits}` : value);
}

function handleInput(event: Event): void {
    const input = event.target as HTMLInputElement;

    displayValue.value = input.value;
    emitNormalized(input.value);
}

function handleFocus(): void {
    isFocused.value = true;
    displayValue.value = extractSubscriberDigits(props.modelValue ?? '');
}

function handleBlur(): void {
    isFocused.value = false;

    const digits = extractSubscriberDigits(props.modelValue ?? '');

    displayValue.value = formatDisplay(digits);
    emit('update:modelValue', digits.length === 10 ? `+63${digits}` : '');
}

watch(
    () => props.modelValue,
    (value): void => {
        if (isFocused.value) {
            return;
        }

        displayValue.value = formatDisplay(
            extractSubscriberDigits(value ?? ''),
        );
    },
    { immediate: true },
);

watch(displayValue, (value): void => {
    if (!isFocused.value) {
        return;
    }

    emitNormalized(value);
});

onMounted((): void => {
    if (props.autofocus) {
        inputRef.value?.focus();
    }
});
</script>

<template>
    <div
        class="flex items-center overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-slate-900"
        :class="[
            disabled ? 'opacity-50' : '',
            error
                ? 'border-rose-300 dark:border-rose-700'
                : 'border-slate-200 focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100 dark:border-slate-800 dark:focus-within:border-violet-600 dark:focus-within:ring-violet-950',
        ]"
    >
        <span
            class="inline-flex shrink-0 items-center gap-1.5 border-r border-slate-200 px-3 py-2 text-sm text-slate-500 select-none dark:border-slate-800 dark:text-slate-400"
        >
            🇵🇭 +63
        </span>
        <input
            ref="inputRef"
            :value="displayValue"
            type="tel"
            class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm text-slate-950 outline-none placeholder:text-slate-400 dark:text-slate-50 dark:placeholder:text-slate-500"
            :data-testid="inputTestId"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :placeholder="placeholder"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
        />
    </div>
</template>
