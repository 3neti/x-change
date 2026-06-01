<script setup lang="ts">
import { formFlowFieldRendererKind  } from '../formFlow';
import type {FormFlowField} from '../formFlow';
import { emitSelectElementValue } from './fieldInputEvents';
import ReadonlyFieldRendererShell from './ReadonlyFieldRendererShell.vue';

const props = defineProps<{
    field: FormFlowField;
    value?: unknown;
}>();

const emit = defineEmits<{
    'update:value': [value: unknown];
}>();

type SelectOption = {
    label: string;
    value: string;
};

function normalizedOptions(): SelectOption[] {
    const options = Array.isArray(props.field.options)
        ? props.field.options
        : [];

    return options
        .map((option): SelectOption | null => {
            if (typeof option === 'string') {
                return {
                    label: option,
                    value: option,
                };
            }

            if (
                option
                && typeof option === 'object'
                && 'value' in option
            ) {
                const value = String((option as Record<string, unknown>).value);
                const label = String((option as Record<string, unknown>).label ?? value);

                return { label, value };
            }

            return null;
        })
        .filter((option): option is SelectOption => option !== null);
}
</script>

<template>
    <ReadonlyFieldRendererShell
        :field="props.field"
        :value="props.value"
        :kind="formFlowFieldRendererKind(props.field.type ?? 'text')"
        test-id="select-field-renderer"
    />
    <select
        data-testid="select-field-renderer-input"
        :value="props.value ?? ''"
        @change="emitSelectElementValue(emit, $event)"
    >
        <option value="">
            Select an option
        </option>

        <option
            v-for="option in normalizedOptions()"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
