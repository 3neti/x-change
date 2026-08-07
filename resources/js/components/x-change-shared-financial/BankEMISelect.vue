<script setup lang="ts">
import { computed, ref } from 'vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BANKS, getPopularEMIs, getBanksByRail } from '@/data/banks';

interface Props {
    modelValue?: string;
    settlementRail?: string | null;
    disabled?: boolean;
    institutions?: MoneyIssuerOption[];
}

export interface MoneyIssuerOption {
    key: string;
    value: string;
    name: string;
    short_name: string;
    category: string;
    account_label: string;
    identifier_scheme: string;
    aliases: string[];
    commonly_used: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
    settlementRail: null,
    disabled: false,
    institutions: () => [],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const localValue = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value),
});

const search = ref('');

// Filter banks by selected settlement rail
const availableBanks = computed(() => {
    const rail = props.settlementRail;
    if (!rail || rail === 'auto') {
        return BANKS;
    }
    return getBanksByRail(rail as 'INSTAPAY' | 'PESONET');
});

const canonicalInstitutions = computed<MoneyIssuerOption[]>(() => {
    if (props.institutions.length > 0) {
        return props.institutions;
    }

    return availableBanks.value.map((bank) => ({
        key: bank.code,
        value: bank.code,
        name: bank.name,
        short_name: bank.name,
        category: getPopularEMIs().some((emi) => emi.code === bank.code) ? 'wallet' : 'bank',
        account_label: 'Account Number',
        identifier_scheme: 'account_number',
        aliases: [],
        commonly_used: getPopularEMIs().some((emi) => emi.code === bank.code),
    }));
});

const matchingInstitutions = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase();

    if (needle === '') {
        return canonicalInstitutions.value;
    }

    return canonicalInstitutions.value.filter((institution) =>
        [institution.name, institution.short_name, ...institution.aliases]
            .join(' ')
            .toLocaleLowerCase()
            .includes(needle),
    );
});

const commonInstitutions = computed(() => matchingInstitutions.value.filter((institution) => institution.commonly_used));
const otherInstitutions = computed(() => matchingInstitutions.value.filter((institution) => !institution.commonly_used));

</script>

<template>
    <div class="grid gap-2">
        <input
            v-if="canonicalInstitutions.length > 8"
            v-model="search"
            type="search"
            :disabled="disabled"
            autocomplete="off"
            placeholder="Search bank or wallet"
            aria-label="Search bank or wallet"
            class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-blue-950"
        />
        <Select v-model="localValue" :disabled="disabled">
        <SelectTrigger class="h-10 text-sm font-semibold">
            <SelectValue placeholder="Choose bank or wallet" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup v-if="commonInstitutions.length > 0">
                <SelectLabel>Common</SelectLabel>
                <SelectItem
                    v-for="institution in commonInstitutions"
                    :key="institution.key"
                    :value="institution.value"
                >
                    {{ institution.name }}
                </SelectItem>
            </SelectGroup>
            
            <SelectGroup v-if="otherInstitutions.length > 0">
                <SelectLabel>All Institutions</SelectLabel>
                <SelectItem
                    v-for="institution in otherInstitutions"
                    :key="institution.key"
                    :value="institution.value"
                >
                    {{ institution.name }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
        </Select>
    </div>
</template>
