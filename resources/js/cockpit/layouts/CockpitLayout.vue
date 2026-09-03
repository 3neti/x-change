<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, onUnmounted } from 'vue';
import CockpitGlobalHeader from '../components/CockpitGlobalHeader.vue';
import CockpitMobileTabBar from '../components/CockpitMobileTabBar.vue';
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

type FundingProjectionChangedPayload = {
    schema: string;
    event_id: string;
    reason: string;
    occurred_at: string;
};

const processedFundingEvents = new Set<string>();
let balanceRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let balanceRefreshInFlight = false;
const fundingRealtime = props.cockpitHeaderReadModel?.funding_realtime;

if (fundingRealtime?.enabled === true) {
    useEcho<FundingProjectionChangedPayload>(
        fundingRealtime.channel,
        fundingRealtime.event,
        (event) => {
            if (
                event.schema !== 'x-change.funding-projection-changed.v1' ||
                ![
                    'account_funding_settled',
                    'voucher_collection_settled',
                ].includes(event.reason) ||
                processedFundingEvents.has(event.event_id)
            ) {
                return;
            }

            processedFundingEvents.add(event.event_id);

            if (balanceRefreshTimer !== null) {
                clearTimeout(balanceRefreshTimer);
            }

            balanceRefreshTimer = setTimeout(() => {
                if (! balanceRefreshInFlight) {
                    balanceRefreshInFlight = true;
                    router.reload({
                        only: ['cockpit_header_read_model'],
                        onFinish: () => {
                            balanceRefreshInFlight = false;
                        },
                    });
                }

                balanceRefreshTimer = null;
            }, 150);
        },
    );
}

onUnmounted(() => {
    if (balanceRefreshTimer !== null) {
        clearTimeout(balanceRefreshTimer);
    }
});
</script>

<template>
    <div
        class="x-experience-surface bg-background text-foreground min-h-screen"
        data-testid="cockpit-layout"
    >
        <div class="flex min-h-screen min-w-0 flex-col">
            <div class="hidden md:block">
                <CockpitGlobalHeader
                    :institution="institution"
                    :operating-identity="headerOperatingIdentity"
                    :connectivity="connectivity"
                    :balances="headerBalances"
                />
            </div>

            <div
                v-if="cockpitEntryNotice"
                class="mx-4 mt-3 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-950 shadow-sm lg:mx-6 dark:border-orange-900/70 dark:bg-orange-950/30 dark:text-orange-100"
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
                class="flex-1 overflow-y-auto p-4 pb-24 md:pb-4 lg:p-6"
                data-testid="cockpit-workspace"
            >
                <slot />
            </main>

            <CockpitMobileTabBar :active-key="activeNavigation" />
        </div>
    </div>
</template>
