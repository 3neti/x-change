<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue: string;
    verified?: boolean;
    readonly?: boolean;
    autofocus?: boolean;
}>(), {
    verified: false,
    readonly: false,
    autofocus: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const codeWidth = computed(() => `${Math.max(1, props.modelValue.length)}ch`);

function updateCode(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <div class="grid gap-2">
        <label :for="id" class="text-sm font-medium">{{ label }}</label>
        <div
            class="border-input bg-card focus-within:ring-ring/50 flex min-h-14 w-full items-center justify-center overflow-hidden rounded-xl border px-3 font-mono text-lg focus-within:ring-[3px]"
            style="gap: 1ch"
            data-testid="claim-code-field"
        >
            <span v-if="verified" class="shrink-0 whitespace-nowrap" aria-hidden="true" data-testid="claim-code-left-pipes">||</span>
            <input
                :id="id"
                :value="modelValue"
                :readonly="readonly"
                :tabindex="readonly ? -1 : undefined"
                :autofocus="autofocus"
                :required="!readonly"
                :placeholder="verified ? undefined : 'Enter pay code'"
                :style="verified ? { width: codeWidth } : undefined"
                :class="verified ? 'shrink-0' : 'w-full'"
                class="!min-h-0 min-w-0 max-w-full !border-0 !bg-transparent !px-0 py-1 text-center font-mono text-lg tracking-normal text-foreground !shadow-none outline-none focus-visible:!ring-0"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                data-testid="claim-code-input"
                @input="updateCode"
            />
            <span v-if="verified" class="shrink-0 whitespace-nowrap" aria-hidden="true" data-testid="claim-code-right-pipes">||</span>
        </div>
    </div>
</template>
