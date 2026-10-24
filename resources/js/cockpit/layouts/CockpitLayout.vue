<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, onUnmounted } from 'vue';
import CockpitGlobalHeader from '../components/CockpitGlobalHeader.vue';
import CockpitClaimEntryLauncher from '../components/CockpitClaimEntryLauncher.vue';
import CockpitMobileTabBar from '../components/CockpitMobileTabBar.vue';
import type { XChangeQrArtifactData } from '../../components/x-change/qrArtifacts';
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
        mobilePresentation?: 'contained' | 'edge';
    }>(),
    {
        activeNavigation: 'dashboard',
        institution: 'x-change Cockpit',
        connectivity: 'Online',
        mobilePresentation: 'contained',
    },
);

type CockpitSharedProps = {
    cockpit_claim_entry_artifact?: XChangeQrArtifactData | null;
    application_profile?: {
        primary: string;
        navigation: Array<{ key: string; label: string; href: string }>;
    };
    application_journey?: { key: string; label: string } | null;
};

const page = usePage<CockpitSharedProps>();
const claimEntryArtifact = computed(
    () => page.props?.cockpit_claim_entry_artifact ?? null,
);
const profileNavigation = computed(
    () => page.props?.application_profile?.navigation ?? [],
);
const activeJourney = computed(() => page.props?.application_journey ?? null);

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
                if (!balanceRefreshInFlight) {
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

            <nav
                v-if="profileNavigation.length > 0"
                class="flex gap-2 overflow-x-auto border-b border-border bg-card px-4 py-2"
                aria-label="App journeys"
                data-testid="app-profile-navigation"
            >
                <Link
                    v-for="journey in profileNavigation"
                    :key="journey.key"
                    :href="journey.href"
                    class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium text-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-primary"
                    :class="activeJourney?.key === journey.key ? 'bg-primary/10 text-primary' : ''"
                    :aria-current="activeJourney?.key === journey.key ? 'page' : undefined"
                >
                    {{ journey.label }}
                </Link>
            </nav>
            <p v-if="activeJourney" class="border-b border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground" data-testid="active-app-journey">
                {{ activeJourney.label }}
            </p>

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
                :class="[
                    'flex-1 overflow-y-auto pb-24 md:p-4 md:pb-4 lg:p-6',
                    mobilePresentation === 'edge' ? 'p-0' : 'p-4',
                ]"
                data-testid="cockpit-workspace"
                :data-mobile-presentation="mobilePresentation"
            >
                <slot />
            </main>

            <CockpitMobileTabBar :active-key="activeNavigation" />
            <CockpitClaimEntryLauncher :artifact="claimEntryArtifact" />
        </div>
    </div>
</template>
