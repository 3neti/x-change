<script setup lang="ts">
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

function valueClass(balance: CockpitBalanceMetric): string {
    if (valueIsHidden(balance) || !isMonetary(balance)) {
        return 'font-normal text-muted-foreground';
    }

    return `font-medium ${toneClass(balance.tone)}`;
}
</script>

<template>
    <section
        aria-label="Cockpit funding position"
        class="max-w-full min-w-0 overflow-x-auto text-xs text-muted-foreground"
        data-testid="cockpit-balance-hud"
    >
        <div
            class="flex min-w-max items-baseline whitespace-nowrap"
            data-testid="cockpit-balance-strip"
        >
            <template v-for="(balance, index) in balances" :key="balance.key">
                <span
                    v-if="index > 0"
                    class="px-2 text-slate-300 dark:text-slate-700"
                    aria-hidden="true"
                    data-testid="cockpit-balance-separator"
                >
                    ·
                </span>
                <article
                    class="inline-flex items-baseline gap-1"
                    :title="balance.helper"
                    :aria-label="accessibleLabel(balance)"
                    data-testid="cockpit-balance-metric"
                >
                    <span
                        class="font-normal text-muted-foreground"
                        data-testid="cockpit-balance-label"
                    >
                        {{ balance.label }}
                    </span>
                    <span
                        :class="[
                            'tabular-nums tracking-tight',
                            valueClass(balance),
                        ]"
                        data-testid="cockpit-balance-value"
                    >
                        {{ displayedValue(balance) }}
                    </span>
                </article>
            </template>
        </div>
    </section>
</template>
