<script setup lang="ts">
import { formFlowFieldRendererKind } from '../formFlow';
import { emitInputElementValue } from './fieldInputEvents';
import type { FormFlowField } from '../formFlow';
import ReadonlyFieldRendererShell from './ReadonlyFieldRendererShell.vue';

const props = defineProps<{
    field: FormFlowField;
    value?: unknown;
}>();

const emit = defineEmits<{
    'update:value': [value: unknown];
}>();
</script>

<template>
    <ReadonlyFieldRendererShell
        :field="props.field"
        :value="props.value"
        :kind="formFlowFieldRendererKind(props.field.type ?? 'password')"
        test-id="password-field-renderer"
    />
    <input
        :id="`compiled-${props.field.key}`"
        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
        data-testid="password-field-renderer-input"
        type="password"
        :autocomplete="props.field.autocomplete ?? 'off'"
        :inputmode="props.field.inputmode ?? 'text'"
        :placeholder="props.field.label ?? props.field.key"
        :value="props.value ?? ''"
        @input="emitInputElementValue(emit, $event)"
    />
    <p
        v-if="props.field.description"
        class="text-xs leading-relaxed text-muted-foreground"
        data-testid="password-field-renderer-description"
    >
        {{ props.field.description }}
    </p>
</template>
