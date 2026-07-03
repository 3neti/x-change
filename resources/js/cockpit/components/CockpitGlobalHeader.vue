<script setup lang="ts">
import CockpitBalanceHud from './CockpitBalanceHud.vue';
import type { CockpitBalanceMetric } from '../types';

withDefaults(defineProps<{
    institution?: string;
    operatingIdentity?: string;
    connectivity?: string;
    balances?: CockpitBalanceMetric[];
}>(), {
    institution: 'x-change Cockpit',
    operatingIdentity: 'Treasury Operations',
    connectivity: 'Online',
    balances: () => [
        {
            key: 'internal',
            label: 'Internal Balance',
            value: 'Pending read model',
            tone: 'neutral',
        },
        {
            key: 'live',
            label: 'Live Balance',
            value: 'Pending provider',
            tone: 'neutral',
        },
    ],
});
</script>

<template>
    <header
        class="border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95"
        data-testid="cockpit-global-header"
    >
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Settlement Operating System
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1 class="text-lg font-semibold text-slate-950 dark:text-slate-50">
                        {{ institution }}
                    </h1>
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                        {{ connectivity }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Operating as: {{ operatingIdentity }}
                </p>
            </div>

            <CockpitBalanceHud :balances="balances" class="xl:min-w-[34rem]" />
        </div>
    </header>
</template>

