<script setup lang="ts">
import { computed } from 'vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { COUNTRIES, DEFAULT_COUNTRY } from '@/data/countries';

interface Props {
    modelValue?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const localValue = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value),
});
</script>

<template>
    <Select v-model="localValue" :disabled="disabled">
        <SelectTrigger>
            <SelectValue placeholder="Select country" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="country in COUNTRIES"
                :key="country.code"
                :value="country.code"
            >
                {{ country.name }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
