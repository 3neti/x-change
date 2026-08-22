<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import CockpitBalanceHud from './CockpitBalanceHud.vue';
import ExperienceThemePicker from '../../components/x-change/ExperienceThemePicker.vue';
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
        class="border-b border-border bg-card/95 px-4 py-2 text-card-foreground backdrop-blur"
        data-testid="cockpit-global-header"
    >
        <div class="grid min-w-0 gap-1">
            <div
                class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1"
                data-testid="cockpit-global-header-primary"
            >
                <h1 class="text-lg font-semibold text-foreground">
                    {{ institution }}
                </h1>
                <span
                    class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                >
                    {{ connectivity }}
                </span>
                <p
                    class="min-w-0 truncate text-sm text-muted-foreground"
                    :title="`Operating as: ${operatingIdentity}`"
                >
                    Operating as: {{ operatingIdentity }}
                </p>
                <div class="ml-auto flex shrink-0 items-center gap-2">
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
                    <ExperienceThemePicker
                        compact
                        data-testid="cockpit-theme-picker"
                    />
                </div>
            </div>

            <CockpitBalanceHud
                :balances="balances"
                :values-visible="valuesVisible"
            />
        </div>
    </header>
</template>
