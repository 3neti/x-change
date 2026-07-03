<script setup lang="ts">
import CockpitGlobalHeader from '../components/CockpitGlobalHeader.vue';
import CockpitSidebar from '../components/CockpitSidebar.vue';
import type { CockpitBalanceMetric } from '../types';

withDefaults(defineProps<{
    activeNavigation?: string;
    institution?: string;
    operatingIdentity?: string;
    connectivity?: string;
    balances?: CockpitBalanceMetric[];
}>(), {
    activeNavigation: 'dashboard',
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
    <div
        class="min-h-screen bg-slate-100 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
        data-testid="cockpit-layout"
    >
        <div class="flex min-h-screen">
            <CockpitSidebar :active-key="activeNavigation" />

            <div class="flex min-w-0 flex-1 flex-col">
                <CockpitGlobalHeader
                    :institution="institution"
                    :operating-identity="operatingIdentity"
                    :connectivity="connectivity"
                    :balances="balances"
                />

                <main class="flex-1 overflow-y-auto p-4 lg:p-6" data-testid="cockpit-workspace">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

