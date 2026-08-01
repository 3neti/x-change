<script setup lang="ts">
import { computed } from 'vue';
import CockpitGlobalHeader from '../components/CockpitGlobalHeader.vue';
import type { CockpitBalanceMetric, CockpitHeaderReadModel } from '../types';

const props = withDefaults(
    defineProps<{
        activeNavigation?: string;
        institution?: string;
        operatingIdentity?: string;
        connectivity?: string;
        balances?: CockpitBalanceMetric[];
        cockpitHeaderReadModel?: CockpitHeaderReadModel;
    }>(),
    {
        activeNavigation: 'dashboard',
        institution: 'x-change Cockpit',
        connectivity: 'Online',
    },
);

const headerBalances = computed(() => {
    if (Array.isArray(props.balances) && props.balances.length > 0) {
        return props.balances;
    }

    const sharedBalances = props.cockpitHeaderReadModel?.balances;

    return Array.isArray(sharedBalances) && sharedBalances.length > 0
        ? sharedBalances
        : undefined;
});
const headerOperatingIdentity = computed(
    () =>
        props.operatingIdentity ??
        props.cockpitHeaderReadModel?.operating_identity ??
        'Account holder',
);
</script>

<template>
    <div
        class="min-h-screen bg-slate-100 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
        data-testid="cockpit-layout"
    >
        <div class="flex min-h-screen min-w-0 flex-col">
            <CockpitGlobalHeader
                :institution="institution"
                :operating-identity="headerOperatingIdentity"
                :connectivity="connectivity"
                :balances="headerBalances"
            />

            <main
                class="flex-1 overflow-y-auto p-4 lg:p-6"
                data-testid="cockpit-workspace"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
