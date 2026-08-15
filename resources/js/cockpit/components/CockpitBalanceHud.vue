<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitBalanceMetric } from '../types';

const props = withDefaults(
    defineProps<{
        balances: CockpitBalanceMetric[];
        valuesVisible?: boolean;
    }>(),
    {
        valuesVisible: false,
    },
);

const hasProviderLiquidity = computed(() =>
    props.balances.some((balance) => balance.key === 'live'),
);

const gridClass = computed(() =>
    hasProviderLiquidity.value ? 'xl:grid-cols-4' : 'xl:grid-cols-3',
);

const toneClass = (tone: CockpitBalanceMetric['tone'] = 'neutral'): string => {
    return {
        neutral: 'text-slate-950 dark:text-slate-50',
        healthy: 'text-emerald-700 dark:text-emerald-300',
        warning: 'text-amber-700 dark:text-amber-300',
        critical: 'text-rose-700 dark:text-rose-300',
    }[tone];
};

function isMonetary(balance: CockpitBalanceMetric): boolean {
    return typeof balance.amount_minor === 'number';
}

function valueIsHidden(balance: CockpitBalanceMetric): boolean {
    return isMonetary(balance) && !props.valuesVisible;
}

function displayedValue(balance: CockpitBalanceMetric): string {
    if (!valueIsHidden(balance)) {
        return balance.value;
    }

    return balance.value.startsWith('Stale · ') ? 'Stale · ••••••' : '••••••';
}

function accessibleLabel(balance: CockpitBalanceMetric): string {
    const value = valueIsHidden(balance)
        ? balance.value.startsWith('Stale · ')
            ? 'Stale. Value hidden'
            : 'Value hidden'
        : balance.value;

    return balance.helper
        ? `${balance.label}: ${value}. ${balance.helper}`
        : `${balance.label}: ${value}`;
}
</script>

<template>
    <section
        aria-label="Cockpit funding position"
        class="grid grid-cols-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
        :class="gridClass"
        data-testid="cockpit-balance-hud"
    >
        <article
            v-for="(balance, index) in balances"
            :key="balance.key"
            :class="[
                'min-w-0 px-1.5 py-3 sm:px-3 xl:px-4',
                balance.key === 'live'
                    ? 'col-span-3 border-t border-slate-200 bg-slate-50/80 xl:col-span-1 xl:border-l xl:border-t-0 dark:border-slate-800 dark:bg-slate-900/60'
                    : index > 0
                      ? 'border-l border-slate-200 dark:border-slate-800'
                      : '',
            ]"
            :title="balance.helper"
            :aria-label="accessibleLabel(balance)"
            data-testid="cockpit-balance-metric"
        >
            <div
                :class="
                    balance.key === 'live'
                        ? 'flex items-center justify-between gap-3 xl:block'
                        : 'block'
                "
            >
                <p
                    class="min-h-7 text-center text-[0.58rem] font-semibold uppercase leading-tight tracking-[0.08em] opacity-70 sm:min-h-8 sm:text-[0.65rem] sm:tracking-[0.12em] xl:min-h-0"
                    :class="
                        balance.key === 'live'
                            ? 'min-h-0 text-left xl:text-center'
                            : ''
                    "
                    data-testid="cockpit-balance-label"
                >
                    {{ balance.label }}
                </p>
                <p
                    :class="[
                        'mt-1 whitespace-nowrap text-center text-[clamp(0.8rem,4vw,1.5rem)] font-bold tabular-nums leading-none tracking-tight',
                        balance.key === 'live' ? 'mt-0 xl:mt-1' : '',
                        toneClass(balance.tone),
                    ]"
                    data-testid="cockpit-balance-value"
                >
                    {{ displayedValue(balance) }}
                </p>
            </div>
        </article>
    </section>
</template>
