<script setup lang="ts">
import { ChevronDown, RotateCcw, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import CockpitFieldHelp from './CockpitFieldHelp.vue';
import CockpitScheduledPortionsEditor from './CockpitScheduledPortionsEditor.vue';
import type { CockpitScheduledPortion } from './CockpitScheduledPortionsEditor.vue';

export type CockpitValueUseMode = 'whole' | 'fixed' | 'open' | 'named';

const props = withDefaults(
    defineProps<{
        mode: CockpitValueUseMode;
        amount: number;
        currency: string;
        fixedCount: number;
        maxClaims: number;
        minimumClaim: number;
        scheduledCount: number;
        scheduledPortions: CockpitScheduledPortion[];
        scheduledTotal: number;
        scheduledRemaining: number;
        scheduledMinimumAmount: number;
        scheduledAvailable: boolean;
        scheduledUnavailableReason?: string | null;
        scheduledAddDisabledReason?: string | null;
        scheduledValidationMessage?: string | null;
        reusableBalance: boolean;
        storedValueAvailable: boolean;
        storedValueUnavailableReason?: string | null;
        storedValueReplenishable: boolean;
        storedValueMaximumBalance: number;
        storedValueOtpAbove: number | null;
        disabled?: boolean;
    }>(),
    {
        storedValueUnavailableReason: null,
        scheduledAddDisabledReason: null,
        scheduledUnavailableReason: null,
        scheduledValidationMessage: null,
        disabled: false,
    },
);

const emit = defineEmits<{
    mode: [mode: CockpitValueUseMode];
    fixedCount: [count: number];
    maxClaims: [count: number];
    minimumClaim: [amount: number];
    reusableBalance: [enabled: boolean];
    storedValueReplenishable: [enabled: boolean];
    storedValueMaximumBalance: [amount: number];
    storedValueOtpAbove: [amount: number | null];
    scheduledAdd: [];
    scheduledRemove: [index: number];
    scheduledUpdate: [
        index: number,
        key: keyof CockpitScheduledPortion,
        value: string,
    ];
}>();

const open = ref(false);
const closeButton = ref<HTMLButtonElement | null>(null);

const normalizedCurrency = computed(() => props.currency.trim() || 'PHP');

function formatMoney(value: number): string {
    const formatted = Math.max(0, value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return normalizedCurrency.value === 'PHP'
        ? `₱${formatted}`
        : `${normalizedCurrency.value} ${formatted}`;
}

const modeSummary = computed(() => {
    if (props.reusableBalance) {
        return `Stored value · ${formatMoney(props.amount)}`;
    }

    if (props.mode === 'fixed') {
        return `Equal · ${props.fixedCount} × ${formatMoney(props.amount / Math.max(1, props.fixedCount))}`;
    }

    if (props.mode === 'open') {
        return `Flexible · up to ${props.maxClaims} · min ${formatMoney(props.minimumClaim)}`;
    }

    if (props.mode === 'named') {
        return `Scheduled · ${props.scheduledCount} portions`;
    }

    return 'Whole amount';
});

function openEditor(): void {
    if (props.disabled) {
        return;
    }

    open.value = true;
}

function closeEditor(): void {
    open.value = false;
}

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    await nextTick();
    closeButton.value?.focus();
});

function selectMode(mode: CockpitValueUseMode): void {
    emit('mode', mode);
}

function updatePositiveInteger(event: Event, type: 'fixed' | 'maximum'): void {
    const value = Math.max(
        1,
        Math.round(Number((event.target as HTMLInputElement).value) || 1),
    );

    if (type === 'fixed') {
        emit('fixedCount', value);

        return;
    }

    emit('maxClaims', value);
}

function updateMoney(
    event: Event,
    type: 'minimum' | 'maximum_balance' | 'otp',
): void {
    const raw = (event.target as HTMLInputElement).value;
    const value = Number(raw);

    if (type === 'otp') {
        emit(
            'storedValueOtpAbove',
            raw === '' ? null : Math.max(0, value || 0),
        );

        return;
    }

    if (type === 'minimum') {
        emit('minimumClaim', Math.max(0, value || 0));

        return;
    }

    emit('storedValueMaximumBalance', Math.max(0, value || 0));
}

function toggleReusableBalance(event: Event): void {
    emit('reusableBalance', (event.target as HTMLInputElement).checked);
}
</script>

<template>
    <div
        class="@container grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
        data-testid="cockpit-value-use-control"
    >
        <div class="flex items-center gap-1">
            <span>Value Use</span>
            <CockpitFieldHelp
                label="About Value Use"
                tooltip="Choose whether this value is claimed once, divided into portions, or activated as a reusable balance."
            />
        </div>
        <div
            class="@sm:grid-cols-[minmax(0,1fr)_auto] grid min-w-0 gap-1.5 rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <button
                type="button"
                class="flex h-8 min-w-0 flex-1 items-center justify-between gap-2 rounded-md px-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-55 dark:text-slate-200 dark:hover:bg-slate-800"
                :disabled="disabled"
                :aria-expanded="open"
                aria-haspopup="dialog"
                data-testid="cockpit-value-use-trigger"
                @click="openEditor"
            >
                <span class="min-w-0 truncate">{{ modeSummary }}</span>
                <ChevronDown class="size-3.5 shrink-0" aria-hidden="true" />
            </button>
            <label
                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-slate-200 px-2 text-[0.7rem] font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300"
                :class="{
                    'cursor-not-allowed opacity-55':
                        disabled || !storedValueAvailable,
                }"
                :title="
                    storedValueAvailable
                        ? 'Activate once, then spend from the remaining balance.'
                        : (storedValueUnavailableReason ?? undefined)
                "
            >
                <input
                    type="checkbox"
                    class="rounded border-slate-300"
                    :checked="reusableBalance"
                    :disabled="disabled || !storedValueAvailable"
                    data-testid="cockpit-value-use-reusable-balance"
                    @change="toggleReusableBalance"
                />
                <span>Reusable balance</span>
            </label>
        </div>
        <p
            v-if="!storedValueAvailable && storedValueUnavailableReason"
            class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
            data-testid="cockpit-value-use-stored-value-unavailable"
        >
            {{ storedValueUnavailableReason }}
        </p>

        <Teleport to="body">
            <button
                v-if="open"
                type="button"
                class="fixed inset-0 z-40 cursor-default bg-slate-950/45"
                aria-label="Close value use editor"
                data-testid="cockpit-value-use-backdrop"
                @click="closeEditor"
            />
            <section
                v-if="open"
                class="fixed inset-x-3 bottom-3 z-50 mx-auto flex max-h-[calc(100vh-1.5rem)] max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:inset-x-auto sm:bottom-auto sm:left-1/2 sm:top-16 sm:w-[min(36rem,calc(100vw-2rem))] sm:-translate-x-1/2 dark:border-slate-800 dark:bg-slate-950"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cockpit-value-use-title"
                data-testid="cockpit-value-use-dialog"
                @keydown.esc="closeEditor"
            >
                <header
                    class="flex items-start justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800"
                >
                    <div>
                        <h3
                            id="cockpit-value-use-title"
                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                        >
                            How may this value be used?
                        </h3>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            The selected plan is enforced when the Pay Code is
                            claimed.
                        </p>
                    </div>
                    <button
                        ref="closeButton"
                        type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-900"
                        aria-label="Close value use editor"
                        @click="closeEditor"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </header>

                <div class="min-h-0 overflow-y-auto p-4">
                    <div
                        v-if="!reusableBalance"
                        class="grid gap-2 sm:grid-cols-2"
                        role="radiogroup"
                        aria-label="Claim plan"
                    >
                        <button
                            v-for="option in [
                                {
                                    value: 'whole',
                                    label: 'Whole amount',
                                    helper: 'One claim uses the entire Pay Code.',
                                },
                                {
                                    value: 'fixed',
                                    label: 'Equal portions',
                                    helper: 'Divide the value into equal claims.',
                                },
                                {
                                    value: 'open',
                                    label: 'Flexible amounts',
                                    helper: 'Allow varying partial claim amounts.',
                                },
                                {
                                    value: 'named',
                                    label: 'Scheduled portions',
                                    helper: 'Set each amount, label, and availability window.',
                                },
                            ]"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :disabled="
                                disabled ||
                                (option.value === 'named' &&
                                    !scheduledAvailable)
                            "
                            :title="
                                option.value === 'named' && !scheduledAvailable
                                    ? (scheduledUnavailableReason ?? undefined)
                                    : undefined
                            "
                            :aria-checked="mode === option.value"
                            class="rounded-xl border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                mode === option.value
                                    ? 'border-emerald-400 bg-emerald-50 text-emerald-950 ring-1 ring-emerald-200 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100'
                                    : 'border-slate-200 text-slate-700 hover:border-emerald-200 hover:bg-emerald-50/50 dark:border-slate-800 dark:text-slate-200 dark:hover:border-emerald-900'
                            "
                            :data-testid="`cockpit-value-use-mode-${option.value}`"
                            @click="
                                selectMode(option.value as CockpitValueUseMode)
                            "
                        >
                            <span class="block text-xs font-semibold">{{
                                option.label
                            }}</span>
                            <span
                                class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ option.helper }}
                            </span>
                        </button>
                    </div>

                    <p
                        v-if="
                            !reusableBalance &&
                            !scheduledAvailable &&
                            scheduledUnavailableReason
                        "
                        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                        data-testid="cockpit-value-use-scheduled-unavailable"
                    >
                        {{ scheduledUnavailableReason }}
                    </p>

                    <div
                        v-if="!reusableBalance && mode === 'fixed'"
                        class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2 dark:bg-slate-900"
                    >
                        <label class="grid gap-1 text-xs font-medium">
                            Number of portions
                            <input
                                type="number"
                                min="1"
                                step="1"
                                :value="fixedCount"
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                data-testid="cockpit-value-use-fixed-count"
                                @input="updatePositiveInteger($event, 'fixed')"
                            />
                        </label>
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                        >
                            <p class="text-[11px] text-slate-500">Plan</p>
                            <p class="mt-1 text-sm font-semibold">
                                {{ fixedCount }} ×
                                {{
                                    formatMoney(
                                        amount / Math.max(1, fixedCount),
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Final-cent differences are reconciled
                                deterministically.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="!reusableBalance && mode === 'open'"
                        class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2 dark:bg-slate-900"
                    >
                        <label class="grid gap-1 text-xs font-medium">
                            Maximum claims
                            <input
                                type="number"
                                min="1"
                                step="1"
                                :value="maxClaims"
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                data-testid="cockpit-value-use-max-claims"
                                @input="
                                    updatePositiveInteger($event, 'maximum')
                                "
                            />
                        </label>
                        <label class="grid gap-1 text-xs font-medium">
                            Minimum claim
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                :value="minimumClaim"
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                                data-testid="cockpit-value-use-minimum-claim"
                                @input="updateMoney($event, 'minimum')"
                            />
                        </label>
                    </div>

                    <CockpitScheduledPortionsEditor
                        v-if="!reusableBalance && mode === 'named'"
                        class="mt-4"
                        :portions="scheduledPortions"
                        :currency="currency"
                        :total="scheduledTotal"
                        :remaining="scheduledRemaining"
                        :minimum-amount="scheduledMinimumAmount"
                        :add-disabled-reason="scheduledAddDisabledReason"
                        :validation-message="scheduledValidationMessage"
                        :disabled="disabled"
                        compact
                        @add="emit('scheduledAdd')"
                        @remove="emit('scheduledRemove', $event)"
                        @update="
                            (index, key, value) =>
                                emit('scheduledUpdate', index, key, value)
                        "
                    />

                    <div
                        v-if="reusableBalance"
                        class="grid gap-3 rounded-xl border border-violet-200 bg-violet-50/60 p-3 sm:grid-cols-2 dark:border-violet-900 dark:bg-violet-950/30"
                        data-testid="cockpit-value-use-stored-value-settings"
                    >
                        <div class="sm:col-span-2">
                            <p
                                class="text-xs font-semibold text-violet-950 dark:text-violet-100"
                            >
                                Reusable balance
                            </p>
                            <p
                                class="mt-1 text-[11px] text-violet-800 dark:text-violet-200"
                            >
                                Activation does not disburse the starting
                                balance.
                            </p>
                        </div>
                        <label class="grid gap-1 text-xs font-medium">
                            Starting balance
                            <span
                                class="flex h-10 items-center rounded-lg border border-violet-200 bg-white px-3 text-sm font-semibold dark:border-violet-900 dark:bg-slate-950"
                            >
                                {{ formatMoney(amount) }}
                            </span>
                        </label>
                        <label class="grid gap-1 text-xs font-medium">
                            Maximum balance
                            <input
                                type="number"
                                :min="amount"
                                step="0.01"
                                :value="storedValueMaximumBalance"
                                class="h-10 rounded-lg border border-violet-200 bg-white px-3 text-sm dark:border-violet-900 dark:bg-slate-950"
                                data-testid="cockpit-value-use-maximum-balance"
                                @input="updateMoney($event, 'maximum_balance')"
                            />
                        </label>
                        <label
                            class="inline-flex items-center gap-2 text-xs font-medium"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300"
                                :checked="storedValueReplenishable"
                                data-testid="cockpit-value-use-replenishable"
                                @change="
                                    emit(
                                        'storedValueReplenishable',
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            Allow governed top-ups
                        </label>
                        <label class="grid gap-1 text-xs font-medium">
                            Require OTP above
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                :value="storedValueOtpAbove ?? ''"
                                class="h-10 rounded-lg border border-violet-200 bg-white px-3 text-sm dark:border-violet-900 dark:bg-slate-950"
                                data-testid="cockpit-value-use-otp-above"
                                @input="updateMoney($event, 'otp')"
                            />
                        </label>
                    </div>
                </div>

                <footer
                    class="flex items-center justify-between gap-3 border-t border-slate-200 p-3 dark:border-slate-800"
                >
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900"
                        @click="selectMode('whole')"
                    >
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                        Whole amount
                    </button>
                    <button
                        type="button"
                        class="h-9 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                        data-testid="cockpit-value-use-done"
                        :disabled="
                            mode === 'named' &&
                            scheduledValidationMessage !== null
                        "
                        @click="closeEditor"
                    >
                        Done
                    </button>
                </footer>
            </section>
        </Teleport>
    </div>
</template>
