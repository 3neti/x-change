<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import CockpitBalanceHud from './CockpitBalanceHud.vue';
import type { CockpitBalanceMetric } from '../types';

withDefaults(
    defineProps<{
        institution?: string;
        operatingIdentity?: string;
        connectivity?: string;
        balances?: CockpitBalanceMetric[];
    }>(),
    {
        institution: 'x-change Cockpit',
        operatingIdentity: 'Account holder',
        connectivity: 'Online',
        balances: () => [
            {
                key: 'internal',
                label: 'Client Funds',
                value: 'Client Funds not connected',
                tone: 'neutral',
            },
            {
                key: 'outstanding',
                label: 'Outstanding Pay Codes',
                value: 'Liability summary not connected',
                tone: 'neutral',
            },
            {
                key: 'issuance',
                label: 'Issuance Capacity',
                value: 'Not available',
                tone: 'neutral',
            },
        ],
    },
);

const valuesVisible = ref(false);

function toggleValueVisibility(): void {
    valuesVisible.value = !valuesVisible.value;
}
</script>

<template>
    <header
        class="border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95"
        data-testid="cockpit-global-header"
    >
        <div
            class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="min-w-0">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400"
                >
                    Settlement Operating System
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                    >
                        {{ institution }}
                    </h1>
                    <span
                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                    >
                        {{ connectivity }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:hover:text-white dark:focus-visible:outline-white"
                        :aria-label="
                            valuesVisible
                                ? 'Hide balance values'
                                : 'Show balance values'
                        "
                        :aria-pressed="valuesVisible"
                        :title="
                            valuesVisible
                                ? 'Hide balance values'
                                : 'Show balance values'
                        "
                        data-testid="cockpit-balance-visibility-toggle"
                        @click="toggleValueVisibility"
                    >
                        <EyeOff
                            v-if="valuesVisible"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Eye v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Operating as: {{ operatingIdentity }}
                </p>
            </div>

            <CockpitBalanceHud
                :balances="balances"
                :values-visible="valuesVisible"
                class="xl:min-w-[44rem]"
            />
        </div>
    </header>
</template>
