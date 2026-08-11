<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { BANKS, getCommonInstitutions, getBanksByRail } from '@/data/banks';
import { destinationInstitution, iconAssetForCode } from '@/components/x-change/support/payoutDestinations';
import PayoutDestinationIcon from '@/components/x-change/PayoutDestinationIcon.vue';

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

// Smart control: the same trigger doubles as the placeholder/selected-
// destination display. Icons are supplementary -- the label text is
// always rendered and remains authoritative (see PayoutDestinationIcon).
const selectedInstitution = computed(() =>
    localValue.value ? destinationInstitution(localValue.value) : null,
);

const open = ref(false);
const search = ref('');
const searchInputEl = ref<HTMLInputElement | null>(null);

// Reset the search text on every close so the next open starts fresh, and
// focus the embedded search input on open so typing works immediately
// without requiring an extra tap.
watch(open, async (isOpen) => {
    if (!isOpen) {
        search.value = '';
        return;
    }

    await nextTick();
    searchInputEl.value?.focus();
});

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

    const commonCodes = new Set(getCommonInstitutions().map((institution) => institution.code));

    return availableBanks.value.map((bank) => ({
        key: bank.code,
        value: bank.code,
        name: bank.name,
        short_name: bank.name,
        category: bank.isEMI ? 'wallet' : 'bank',
        account_label: 'Account Number',
        identifier_scheme: 'account_number',
        aliases: [],
        commonly_used: commonCodes.has(bank.code),
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

const selectInstitution = (value: string) => {
    localValue.value = value;
    open.value = false;
};
</script>

<template>
    <!--
        Smart destination control: a single component acts as both the
        search/select input and the selected-destination display, instead
        of stacking a separate always-visible search field above a plain
        select trigger. The search field only appears inside the open
        popover, so the closed state costs one compact row.
    -->
    <div class="relative">
        <button
            type="button"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            class="flex h-11 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-left text-sm font-semibold shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="bank-emi-select-trigger"
            @click="open = !open"
            @keydown.esc="open = false"
        >
            <span
                v-if="selectedInstitution"
                class="flex min-w-0 items-center gap-2"
            >
                <PayoutDestinationIcon
                    :icon-asset="selectedInstitution.iconAsset"
                    :alt="selectedInstitution.label"
                    size-class="h-4 w-4"
                />
                <span class="truncate">{{ selectedInstitution.label }}</span>
            </span>
            <span v-else class="text-muted-foreground">
                Choose wallet or bank
            </span>
            <svg
                class="h-4 w-4 shrink-0 opacity-50"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
            >
                <path
                    d="m6 9 6 6 6-6"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>

        <button
            v-if="open"
            type="button"
            class="fixed inset-0 z-40 cursor-default bg-transparent"
            aria-label="Close bank or wallet picker"
            @click="open = false"
        />

        <div
            v-if="open"
            class="fixed z-50 flex flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-xl"
            style="top: 5rem; left: 50%; width: min(24rem, calc(100vw - 2rem)); max-height: min(30rem, calc(100vh - 7rem)); transform: translateX(-50%);"
            role="listbox"
            aria-label="Bank or wallet"
            @keydown.esc="open = false"
        >
            <div
                class="border-b p-2"
                @keydown.stop
                @click.stop
            >
                <input
                    ref="searchInputEl"
                    v-model="search"
                    type="search"
                    :disabled="disabled"
                    autocomplete="off"
                    placeholder="Search wallet or bank"
                    aria-label="Search wallet or bank"
                    class="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-blue-950"
                    @keydown.esc="open = false"
                />
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-1">
                <div v-if="commonInstitutions.length > 0">
                    <p class="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                        Common
                    </p>
                    <button
                        v-for="institution in commonInstitutions"
                        :key="institution.key"
                        type="button"
                        class="flex h-9 w-full min-w-0 items-center gap-2 rounded-md px-2 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                        role="option"
                        :aria-selected="institution.value === localValue"
                        @click="selectInstitution(institution.value)"
                    >
                        <PayoutDestinationIcon
                            :icon-asset="iconAssetForCode(institution.value)"
                            :alt="institution.name"
                            size-class="h-4 w-4"
                        />
                        <span class="truncate">{{ institution.name }}</span>
                    </button>
                </div>

                <div v-if="otherInstitutions.length > 0">
                    <p class="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                        All Institutions
                    </p>
                    <button
                        v-for="institution in otherInstitutions"
                        :key="institution.key"
                        type="button"
                        class="flex h-9 w-full min-w-0 items-center gap-2 rounded-md px-2 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                        role="option"
                        :aria-selected="institution.value === localValue"
                        @click="selectInstitution(institution.value)"
                    >
                        <PayoutDestinationIcon
                            :icon-asset="iconAssetForCode(institution.value)"
                            :alt="institution.name"
                            size-class="h-4 w-4"
                        />
                        <span class="truncate">{{ institution.name }}</span>
                    </button>
                </div>

                <p
                    v-if="matchingInstitutions.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    No matching wallet or bank
                </p>
            </div>
        </div>
    </div>
</template>
