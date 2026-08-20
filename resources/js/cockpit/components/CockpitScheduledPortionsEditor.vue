<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';

export type CockpitScheduledPortion = {
    id: string;
    amount: string;
    description: string;
    tag: string;
    claim_on: string;
    claim_by: string;
};

const props = withDefaults(
    defineProps<{
        portions: CockpitScheduledPortion[];
        currency: string;
        total: number;
        remaining: number;
        minimumAmount: number;
        addDisabledReason?: string | null;
        validationMessage?: string | null;
        disabled?: boolean;
        compact?: boolean;
    }>(),
    {
        addDisabledReason: null,
        validationMessage: null,
        disabled: false,
        compact: false,
    },
);

const emit = defineEmits<{
    add: [];
    remove: [index: number];
    update: [index: number, key: keyof CockpitScheduledPortion, value: string];
}>();

function update(
    index: number,
    key: keyof CockpitScheduledPortion,
    event: Event,
): void {
    emit('update', index, key, (event.target as HTMLInputElement).value);
}

function formatAmount(value: number): string {
    return value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <div
        class="grid gap-3 rounded-xl border border-cyan-200 bg-cyan-50/70 p-3 dark:border-cyan-900/60 dark:bg-cyan-950/30"
        data-testid="cockpit-scheduled-portions-editor"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p
                    class="text-xs font-semibold text-cyan-950 dark:text-cyan-100"
                >
                    Scheduled claim plan
                </p>
                <p class="mt-1 text-[11px] text-cyan-800 dark:text-cyan-200">
                    Set each portion's value, label, and availability window.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border border-cyan-200 bg-white px-3 py-1.5 text-xs font-semibold text-cyan-800 shadow-sm hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-cyan-800 dark:bg-slate-950 dark:text-cyan-100"
                data-testid="cockpit-scheduled-portions-add"
                :disabled="disabled || addDisabledReason !== null"
                @click="emit('add')"
            >
                <Plus class="size-3.5" aria-hidden="true" />
                Add portion
            </button>
        </div>

        <p
            v-if="addDisabledReason"
            class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
            data-testid="cockpit-scheduled-portions-add-warning"
        >
            {{ addDisabledReason }}
        </p>

        <div class="grid gap-3">
            <article
                v-for="(portion, index) in portions"
                :key="portion.id"
                class="grid gap-3 rounded-xl border border-cyan-100 bg-white p-3 shadow-sm dark:border-cyan-900/50 dark:bg-slate-950"
                :data-testid="`cockpit-scheduled-portion-${index}`"
            >
                <div class="flex items-center justify-between gap-3">
                    <p
                        class="text-xs font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Portion {{ index + 1 }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 disabled:cursor-not-allowed disabled:text-slate-400 dark:text-rose-300"
                        :data-testid="`cockpit-scheduled-portion-${index}-remove`"
                        :disabled="disabled || portions.length <= 1"
                        @click="emit('remove', index)"
                    >
                        <Trash2 class="size-3" aria-hidden="true" />
                        Remove
                    </button>
                </div>

                <div
                    class="grid min-w-0 grid-cols-1 gap-3"
                    :class="compact ? 'sm:grid-cols-2' : 'lg:grid-cols-6'"
                >
                    <label
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                    >
                        Amount
                        <input
                            :value="portion.amount"
                            type="number"
                            :min="minimumAmount"
                            step="0.01"
                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            :data-testid="`cockpit-scheduled-portion-${index}-amount`"
                            :disabled="disabled"
                            @input="update(index, 'amount', $event)"
                        />
                    </label>
                    <label
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        :class="compact ? '' : 'lg:col-span-2'"
                    >
                        Label
                        <input
                            :value="portion.description"
                            type="text"
                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            :data-testid="`cockpit-scheduled-portion-${index}-description`"
                            :disabled="disabled"
                            @input="update(index, 'description', $event)"
                        />
                    </label>
                    <label
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                    >
                        Category
                        <input
                            :value="portion.tag"
                            type="text"
                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            :data-testid="`cockpit-scheduled-portion-${index}-tag`"
                            :disabled="disabled"
                            @input="update(index, 'tag', $event)"
                        />
                    </label>
                    <label
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                    >
                        Available on
                        <input
                            :value="portion.claim_on"
                            type="date"
                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            :data-testid="`cockpit-scheduled-portion-${index}-claim-on`"
                            :disabled="disabled"
                            @input="update(index, 'claim_on', $event)"
                        />
                    </label>
                    <label
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                    >
                        Expires on
                        <input
                            :value="portion.claim_by"
                            type="date"
                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            :data-testid="`cockpit-scheduled-portion-${index}-claim-by`"
                            :disabled="disabled"
                            @input="update(index, 'claim_by', $event)"
                        />
                    </label>
                </div>
            </article>
        </div>

        <div class="grid gap-2 text-xs sm:grid-cols-2">
            <div class="rounded-lg bg-white p-3 dark:bg-slate-950">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Scheduled total
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ currency || 'PHP' }} {{ formatAmount(total) }}
                </p>
            </div>
            <div class="rounded-lg bg-white p-3 dark:bg-slate-950">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Unassigned value
                </p>
                <p
                    class="mt-1 font-semibold"
                    :class="
                        Math.abs(remaining) < 0.01
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-rose-700 dark:text-rose-300'
                    "
                    data-testid="cockpit-scheduled-portions-remaining"
                >
                    {{ currency || 'PHP' }} {{ formatAmount(remaining) }}
                </p>
            </div>
        </div>

        <p
            v-if="validationMessage"
            class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
            data-testid="cockpit-scheduled-portions-error"
        >
            {{ validationMessage }}
        </p>
    </div>
</template>
