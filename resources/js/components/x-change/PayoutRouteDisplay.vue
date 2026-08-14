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
    payoutRouteSentence,
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
const sentence = computed(() => payoutRouteSentence({
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
            <div class="min-w-0 flex-1 space-y-3">
                <div class="space-y-1">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">
                        Money route
                    </p>
                    <p class="text-sm font-semibold leading-snug text-foreground">
                        {{ sentence }}
                    </p>
                </div>

                <div
                    class="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap text-[11px] font-semibold sm:text-xs"
                    data-testid="payout-route-segments"
                >
                    <template
                        v-for="(segment, index) in routeSegments"
                        :key="`${segment}-${index}`"
                    >
                        <span
                            class="inline-flex min-h-7 min-w-0 items-center gap-1.5 rounded-full border border-border bg-background px-2 text-foreground"
                            :class="{
                                'shrink-0 border-primary/25 bg-primary/10 text-primary': index === 0,
                                'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200': segment === rail,
                                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200': segment === institution.shortLabel,
                                'shrink font-mono': index === routeSegments.length - 1,
                            }"
                        >
                            <PayoutDestinationIcon
                                :icon-asset="routeIcons[index]"
                                :fallback-icon="fallbackIconFor(index, segment)"
                                :alt="segment"
                                size-class="h-3.5 w-3.5"
                            />
                            <span class="min-w-0 truncate">{{ segment }}</span>
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
