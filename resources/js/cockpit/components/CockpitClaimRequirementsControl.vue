<script setup lang="ts">
import { Lock, Plus, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import type { LucideIcon } from 'lucide-vue-next';
import CockpitFieldHelp from './CockpitFieldHelp.vue';

export type CockpitClaimRequirementCategory =
    | 'evidence'
    | 'verification'
    | 'details';

export type CockpitClaimRequirementOption = {
    value: string;
    label: string;
    icon: LucideIcon;
    category: CockpitClaimRequirementCategory;
    helper: string;
    selected: boolean;
    locked: boolean;
    lockedReason: string | null;
    disabled: boolean;
    unavailable: boolean;
    unavailableReason: string | null;
    priceLabel?: string | null;
};

export type CockpitClaimRequirementPreset = {
    key: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        options: CockpitClaimRequirementOption[];
        presets?: CockpitClaimRequirementPreset[];
        disabled?: boolean;
        helpTooltip?: string;
    }>(),
    {
        presets: () => [],
        disabled: false,
        helpTooltip: '',
    },
);

const emit = defineEmits<{
    toggle: [value: string, selected: boolean];
    preset: [key: string];
}>();

const open = ref(false);
const search = ref('');
const searchInputEl = ref<HTMLInputElement | null>(null);

// Small, search-only alias map. This never introduces a competing selection
// model: matches are only used to filter the existing catalog, and every
// selection still resolves to one of props.options' canonical `value`s.
const searchAliases: Record<string, string[]> = {
    name: ['full name', 'legal name'],
    email: ['e-mail'],
    address: ['home address', 'mailing address'],
    reference_code: ['ref code', 'branch reference', 'reference'],
    birth_date: ['birthday', 'dob'],
    gross_monthly_income: ['income', 'salary'],
    mobile: ['phone', 'cellphone', 'cell', 'sms'],
    otp: ['one-time passcode', 'one time pin', 'code'],
    kyc: ['identity', 'id check', 'verification'],
    selfie: ['photo', 'picture', 'face'],
    signature: ['sign', 'e-signature'],
    location: ['gps', 'geolocation'],
};

const claimRequirementGroups: Array<{
    key: CockpitClaimRequirementCategory;
    label: string;
}> = [
    { key: 'evidence', label: 'Evidence' },
    { key: 'verification', label: 'Verification' },
    { key: 'details', label: 'Details' },
];

const claimRequirementOptionOrder = [
    'selfie',
    'signature',
    'location',
    'kyc',
    'otp',
    'name',
    'mobile',
    'email',
    'address',
    'birth_date',
    'gross_monthly_income',
    'reference_code',
];

const claimRequirementOptionRank = new Map(
    claimRequirementOptionOrder.map((value, index) => [value, index]),
);

const selectedChips = computed<CockpitClaimRequirementOption[]>(() =>
    props.options.filter((option) => option.selected),
);

function openPopover(): void {
    if (props.disabled) {
        return;
    }

    open.value = true;
}

function closePopover(): void {
    open.value = false;
}

watch(open, async (isOpen) => {
    if (!isOpen) {
        search.value = '';

        return;
    }

    await nextTick();
    searchInputEl.value?.focus();
});

function matchesSearch(option: CockpitClaimRequirementOption): boolean {
    const needle = search.value.trim().toLocaleLowerCase();

    if (needle === '') {
        return true;
    }

    const haystack = [
        option.label,
        option.value,
        ...(searchAliases[option.value] ?? []),
    ]
        .join(' ')
        .toLocaleLowerCase();

    return haystack.includes(needle);
}

const matchingOptions = computed<CockpitClaimRequirementOption[]>(() =>
    props.options
        .filter((option) => matchesSearch(option))
        .sort(
            (left, right) =>
                (claimRequirementOptionRank.get(left.value) ??
                    Number.MAX_SAFE_INTEGER) -
                (claimRequirementOptionRank.get(right.value) ??
                    Number.MAX_SAFE_INTEGER),
        ),
);

const matchingGroups = computed(() =>
    claimRequirementGroups
        .map((group) => ({
            ...group,
            options: matchingOptions.value.filter(
                (option) => option.category === group.key,
            ),
        }))
        .filter((group) => group.options.length > 0),
);

function handleToggle(
    option: CockpitClaimRequirementOption,
    checked: boolean,
): void {
    if (option.disabled) {
        return;
    }

    emit('toggle', option.value, checked);
}

function removeChip(option: CockpitClaimRequirementOption): void {
    if (option.locked || props.disabled) {
        return;
    }

    emit('toggle', option.value, false);
}

function applyPreset(key: string): void {
    emit('preset', key);
}
</script>

<template>
    <div
        class="@container relative grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
        data-testid="cockpit-claim-requirements-control"
    >
        <div
            class="@sm:flex @sm:h-9 @sm:flex-nowrap @sm:py-0 grid min-h-9 min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-1.5 gap-y-1 rounded-lg border border-slate-200 bg-white px-1 py-1 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex shrink-0 items-center gap-0.5">
                <button
                    type="button"
                    class="shrink-0 rounded-md px-1.5 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-55 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="disabled"
                    :aria-expanded="open"
                    aria-haspopup="dialog"
                    data-testid="cockpit-claim-requirements-trigger"
                    @click="openPopover"
                >
                    Claim Requirements
                </button>
                <CockpitFieldHelp
                    v-if="helpTooltip !== ''"
                    label="About Claim Requirements"
                    :tooltip="helpTooltip"
                />
            </div>

            <div
                class="@sm:col-auto @sm:row-auto @sm:flex-1 col-span-2 row-start-2 flex min-w-0 max-w-full items-center gap-1 overflow-x-auto"
                data-testid="cockpit-claim-requirements-chips"
            >
                <span
                    v-if="selectedChips.length === 0"
                    class="shrink-0 text-[11px] text-slate-400 dark:text-slate-500"
                    data-testid="cockpit-claim-requirements-empty"
                >
                    None selected
                </span>
                <span
                    v-for="chip in selectedChips"
                    :key="chip.value"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border px-2 py-1 text-[0.68rem] font-semibold"
                    :class="
                        chip.locked
                            ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100'
                    "
                    :tabindex="chip.locked ? 0 : undefined"
                    :aria-label="
                        chip.locked
                            ? `${chip.label} is required. ${chip.lockedReason ?? ''}`.trim()
                            : undefined
                    "
                    :title="
                        chip.locked
                            ? (chip.lockedReason ?? undefined)
                            : undefined
                    "
                    :data-testid="`cockpit-claim-requirement-chip-${chip.value}`"
                    :data-locked="chip.locked ? 'true' : 'false'"
                >
                    <component
                        :is="chip.icon"
                        class="size-3"
                        aria-hidden="true"
                    />
                    {{ chip.label }}
                    <Lock
                        v-if="chip.locked"
                        class="size-3"
                        aria-hidden="true"
                        data-testid="cockpit-claim-requirement-chip-lock"
                    />
                    <button
                        v-else
                        type="button"
                        class="rounded p-0.5 text-emerald-700 hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-emerald-300 dark:hover:bg-emerald-900"
                        :disabled="disabled"
                        :aria-label="`Remove ${chip.label} requirement`"
                        data-testid="cockpit-claim-requirement-chip-remove"
                        @click.stop="removeChip(chip)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>
            </div>

            <button
                type="button"
                class="@sm:col-auto @sm:row-auto col-start-2 row-start-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-55 dark:text-slate-400 dark:hover:bg-slate-800"
                :disabled="disabled"
                aria-label="Add claim requirement"
                data-testid="cockpit-claim-requirements-add"
                @click="openPopover"
            >
                <Plus class="size-4" aria-hidden="true" />
            </button>
        </div>

        <button
            v-if="open"
            type="button"
            class="fixed inset-0 z-40 cursor-default bg-slate-950/40"
            aria-label="Close claim requirements picker"
            data-testid="cockpit-claim-requirements-backdrop"
            @click="closePopover"
        />

        <div
            v-if="open"
            class="fixed z-50 flex max-h-[min(32rem,calc(100vh-6rem))] w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-2xl dark:border-slate-800 dark:bg-slate-950"
            style="top: 5rem; left: 50%; transform: translateX(-50%)"
            role="dialog"
            aria-modal="true"
            aria-label="Choose claim requirements"
            data-testid="cockpit-claim-requirements-popover"
            @keydown.esc="closePopover"
        >
            <div class="border-b border-slate-200 p-2 dark:border-slate-800">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref="searchInputEl"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        placeholder="Search requirements"
                        aria-label="Search claim requirements"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                        data-testid="cockpit-claim-requirements-search"
                        @keydown.esc="closePopover"
                    />
                </div>
            </div>

            <div
                v-if="presets.length > 0"
                class="grid gap-1.5 border-b border-slate-200 p-2 dark:border-slate-800"
                data-testid="cockpit-claim-requirements-presets"
            >
                <p
                    class="px-1 text-[0.68rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                >
                    Quick sets
                </p>
                <div
                    class="flex flex-wrap gap-1.5"
                    role="group"
                    aria-label="Quick claim requirement sets"
                >
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[0.7rem] font-semibold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        :data-testid="`cockpit-claim-requirements-preset-${preset.key}`"
                        @click="applyPreset(preset.key)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                <fieldset
                    v-for="(group, index) in matchingGroups"
                    :key="group.key"
                    :class="index < matchingGroups.length - 1 ? 'mb-2' : ''"
                    :data-testid="`cockpit-claim-requirements-group-${group.key}`"
                >
                    <legend
                        class="px-1 py-1 text-[0.68rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        {{ group.label }}
                    </legend>
                    <label
                        v-for="option in group.options"
                        :key="option.value"
                        class="flex items-start gap-2 rounded-lg px-1.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900"
                        :class="{
                            'opacity-55': option.unavailable,
                        }"
                        :data-testid="`cockpit-claim-requirement-option-${option.value}`"
                    >
                        <input
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300"
                            :checked="option.selected"
                            :disabled="option.disabled"
                            @change="
                                handleToggle(
                                    option,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <component
                            :is="option.icon"
                            class="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span>{{ option.label }}</span>
                                <span
                                    v-if="option.priceLabel"
                                    class="text-[0.65rem] font-normal text-slate-400"
                                >
                                    {{ option.priceLabel }}
                                </span>
                            </span>
                            <span
                                class="mt-0.5 block text-[11px] font-normal text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    option.unavailable
                                        ? (option.unavailableReason ??
                                          option.helper)
                                        : option.helper
                                }}
                            </span>
                        </span>
                    </label>
                </fieldset>

                <p
                    v-if="matchingOptions.length === 0"
                    class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400"
                >
                    No matching requirement
                </p>
            </div>
        </div>
    </div>
</template>
