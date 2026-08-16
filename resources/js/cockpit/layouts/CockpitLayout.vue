<script setup lang="ts">
import { computed } from 'vue';
import CockpitGlobalHeader from '../components/CockpitGlobalHeader.vue';
import type {
    CockpitBalanceMetric,
    CockpitEntryNotice,
    CockpitHeaderReadModel,
} from '../types';

const props = withDefaults(
    defineProps<{
        activeNavigation?: string;
        institution?: string;
        operatingIdentity?: string;
        connectivity?: string;
        balances?: CockpitBalanceMetric[];
        cockpitHeaderReadModel?: CockpitHeaderReadModel;
        cockpitEntryNotice?: CockpitEntryNotice | null;
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

            <div
                v-if="cockpitEntryNotice"
                class="mx-4 mt-3 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950 shadow-sm dark:border-orange-900/70 dark:bg-orange-950/30 dark:text-orange-100 lg:mx-6"
                role="status"
                data-testid="cockpit-entry-notice"
            >
                <span
                    class="mt-1 h-2 w-2 shrink-0 rounded-full bg-orange-500"
                    aria-hidden="true"
                />
                <span>
                    <strong class="font-semibold">{{
                        cockpitEntryNotice.title
                    }}</strong>
                    <span class="ml-1">{{ cockpitEntryNotice.message }}</span>
                </span>
            </div>

            <main
                class="flex-1 overflow-y-auto p-4 lg:p-6"
                data-testid="cockpit-workspace"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
