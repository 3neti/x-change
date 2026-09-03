<script setup lang="ts">
import { computed } from 'vue';
import { Landmark, Route, Send, WalletCards } from 'lucide-vue-next';
import PayoutDestinationIcon from '@/components/x-change/PayoutDestinationIcon.vue';
import {
    destinationInstitution,
    payoutDestinationRouteIcons,
    payoutDestinationRouteSegments,
    payoutRouteIcons,
    payoutRouteSegments,
    settlementRailLabel,
} from '@/components/x-change/support/payoutDestinations';

const props = withDefaults(defineProps<{
    amount?: string | number | null;
    bankCode?: string | null;
    accountNumber?: string | null;
    settlementRail?: string | null;
    provider?: string | null;
    compact?: boolean;
    mode?: 'redeemer' | 'operational';
}>(), {
    amount: null,
    bankCode: null,
    accountNumber: null,
    settlementRail: 'INSTAPAY',
    provider: 'NetBank',
    compact: false,
    mode: 'redeemer',
});

const institution = computed(() => destinationInstitution(props.bankCode));
const rail = computed(() => settlementRailLabel(props.settlementRail || 'INSTAPAY'));
const routeSegments = computed(() => props.mode === 'operational'
    ? payoutRouteSegments({
        provider: props.provider,
        settlementRail: props.settlementRail || 'INSTAPAY',
        bankCode: props.bankCode,
        accountNumber: props.accountNumber,
    })
    : payoutDestinationRouteSegments({
        amount: props.amount,
        settlementRail: props.settlementRail || 'INSTAPAY',
        bankCode: props.bankCode,
        accountNumber: props.accountNumber,
    }));
const routeIcons = computed(() => props.mode === 'operational'
    ? payoutRouteIcons({
        provider: props.provider,
        settlementRail: props.settlementRail || 'INSTAPAY',
        bankCode: props.bankCode,
        accountNumber: props.accountNumber,
    })
    : payoutDestinationRouteIcons({
        amount: props.amount,
        settlementRail: props.settlementRail || 'INSTAPAY',
        bankCode: props.bankCode,
        accountNumber: props.accountNumber,
    }));
function fallbackIconFor(index: number, segment: string) {
    if (props.mode === 'operational') {
        return index === 0
            ? Send
            : segment === props.provider
                ? Landmark
                : segment === institution.value.shortLabel
                    ? WalletCards
                    : null;
    }

    return segment === institution.value.shortLabel ? WalletCards : null;
}

function shouldShowSegmentText(index: number): boolean {
    const segment = routeSegments.value[index];

    return props.mode === 'operational'
        || index === 0
        || segment === institution.value.shortLabel
        || index === routeSegments.value.length - 1;
}
</script>

<template>
    <section
        class="rounded-xl border border-primary/15 bg-primary/5 p-4"
        data-testid="payout-route-display"
        aria-label="Payout route"
    >
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background text-primary shadow-sm ring-1 ring-primary/10">
                <Route class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1 space-y-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">
                        Money route
                    </p>
                </div>

                <div
                    class="flex min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap text-sm font-semibold"
                    data-testid="payout-route-segments"
                >
                    <template
                        v-for="(segment, index) in routeSegments"
                        :key="`${segment}-${index}`"
                    >
                        <span
                            class="inline-flex min-w-0 items-center gap-1.5 text-foreground"
                            :class="{
                                'shrink-0 text-primary': index === 0,
                                'text-blue-700 dark:text-blue-200': segment === rail,
                                'text-emerald-700 dark:text-emerald-200': segment === institution.shortLabel,
                                'shrink font-mono': index === routeSegments.length - 1,
                            }"
                        >
                            <PayoutDestinationIcon
                                :icon-asset="routeIcons[index]"
                                :fallback-icon="fallbackIconFor(index, segment)"
                                :alt="segment"
                                size-class="h-5 w-5"
                            />
                            <span
                                v-if="shouldShowSegmentText(index)"
                                class="min-w-0 truncate"
                            >
                                {{ segment }}
                            </span>
                        </span>
                        <span
                            v-if="index < routeSegments.length - 1"
                            class="shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        >
                            ->
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>
