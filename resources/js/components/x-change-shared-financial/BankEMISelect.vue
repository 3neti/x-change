<script setup lang="ts">
import { computed } from 'vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BANKS, getPopularEMIs, getBanksByRail } from '@/data/banks';

interface Props {
    modelValue?: string;
    settlementRail?: string | null;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
    settlementRail: null,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const localValue = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value),
});

// Filter banks by selected settlement rail
const availableBanks = computed(() => {
    const rail = props.settlementRail;
    if (!rail || rail === 'auto') {
        return BANKS;
    }
    return getBanksByRail(rail as 'INSTAPAY' | 'PESONET');
});

// Popular EMIs (filtered by rail)
const popularEMIs = computed(() => {
    const popular = getPopularEMIs();
    if (!props.settlementRail || props.settlementRail === 'auto') {
        return popular;
    }
    return popular.filter(emi => 
        availableBanks.value.some(bank => bank.code === emi.code)
    );
});

// Other banks (filtered by rail, excluding popular EMIs)
const otherBanks = computed(() => {
    const popularCodes = popularEMIs.value.map(emi => emi.code);
    return availableBanks.value.filter(bank => !popularCodes.includes(bank.code));
});
</script>

<template>
    <Select v-model="localValue" :disabled="disabled">
        <SelectTrigger class="font-bold text-lg">
            <SelectValue placeholder="Select bank or EMI" />
        </SelectTrigger>
        <SelectContent>
            <!-- Popular EMIs -->
            <SelectGroup v-if="popularEMIs.length > 0">
                <SelectLabel>Popular EMIs</SelectLabel>
                <SelectItem
                    v-for="emi in popularEMIs"
                    :key="emi.code"
                    :value="emi.code"
                >
                    {{ emi.name }}
                </SelectItem>
            </SelectGroup>
            
            <!-- Other Banks -->
            <SelectGroup v-if="otherBanks.length > 0">
                <SelectLabel>Banks</SelectLabel>
                <SelectItem
                    v-for="bank in otherBanks"
                    :key="bank.code"
                    :value="bank.code"
                >
                    {{ bank.name }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
