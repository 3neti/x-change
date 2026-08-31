<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Eye,
    MoreHorizontal,
    Share2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CockpitPayCodeCopyButton from './CockpitPayCodeCopyButton.vue';
import CockpitPayCodeQuickTerminalAction from './CockpitPayCodeQuickTerminalAction.vue';
import { usePayCodeExplorerClock } from '../composables/usePayCodeExplorerClock';
import {
    formatAbsoluteTime,
    formatRelativeTime,
} from '../utils/dateTime';
import type {
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeRowAction,
} from '../types';

const defaultVisibleRecordLimit = 25;
const visibleRecordLimitOptions = [10, 25, 50];
const props = defineProps<{
    records: CockpitPayCodeExplorerRecord[];
    actions: CockpitPayCodeRowAction[];
    visibleLimit?: number;
}>();

const selectedVisibleLimit = ref(
    props.visibleLimit && props.visibleLimit > 0
        ? props.visibleLimit
        : defaultVisibleRecordLimit,
);

const currentPage = ref(1);

const totalPages = computed(() =>
    Math.max(Math.ceil(props.records.length / selectedVisibleLimit.value), 1),
);

const firstVisibleRecordNumber = computed(() =>
    props.records.length === 0
        ? 0
        : (currentPage.value - 1) * selectedVisibleLimit.value + 1,
);

const lastVisibleRecordNumber = computed(() =>
    Math.min(
        currentPage.value * selectedVisibleLimit.value,
        props.records.length,
    ),
);

const visibleRecords = computed(() =>
    props.records.slice(
        firstVisibleRecordNumber.value - 1,
        lastVisibleRecordNumber.value,
    ),
);

const hasLiveExpiry = computed(() =>
    visibleRecords.value.some(
        (record) =>
            !record.operationalStatus.isTerminal &&
            record.timing.expiresAt !== null,
    ),
);
const explorerNow = usePayCodeExplorerClock(hasLiveExpiry);

const hiddenRecordCount = computed(() =>
    Math.max(props.records.length - visibleRecords.value.length, 0),
);

const isResultLimited = computed(() => hiddenRecordCount.value > 0);

const hasPreviousPage = computed(() => currentPage.value > 1);

const hasNextPage = computed(() => currentPage.value < totalPages.value);

watch(
    () => [props.records.length, selectedVisibleLimit.value],
    () => {
        currentPage.value = 1;
    },
);

function goToPreviousPage(): void {
    if (hasPreviousPage.value) {
        currentPage.value -= 1;
    }
}

function goToNextPage(): void {
    if (hasNextPage.value) {
        currentPage.value += 1;
    }
}

function rowActions(
    record: CockpitPayCodeExplorerRecord,
    fallbackActions: CockpitPayCodeRowAction[],
): CockpitPayCodeRowAction[] {
    return record.actions && record.actions.length > 0
        ? record.actions
        : fallbackActions;
}

function isEnabledAction(action: CockpitPayCodeRowAction): boolean {
    return (
        action.enabled === true &&
        typeof action.href === 'string' &&
        action.href.trim() !== ''
    );
}

function enabledActions(
    record: CockpitPayCodeExplorerRecord,
): CockpitPayCodeRowAction[] {
    return rowActions(record, props.actions).filter((action) =>
        isEnabledAction(action),
    );
}

function disabledActions(
    record: CockpitPayCodeExplorerRecord,
): CockpitPayCodeRowAction[] {
    return rowActions(record, props.actions).filter(
        (action) => !isEnabledAction(action),
    );
}

function displayStatus(status: string): string {
    return status
        .split(/[_\s-]+/)
        .filter((part) => part.trim() !== '')
        .map(
            (part) =>
                `${part.charAt(0).toUpperCase()}${part.slice(1).toLowerCase()}`,
        )
        .join(' ');
}

function statusLabel(record: CockpitPayCodeExplorerRecord): string {
    return record.consumerStatus
        ? displayStatus(record.consumerStatus)
        : record.operationalStatus.label || displayStatus(record.status);
}

function statusBadgeClass(status: string): string {
    const normalizedStatus = status.toLowerCase().replaceAll('_', '-');

    if (
        ['active', 'issued', 'ready', 'paid', 'redeemed', 'completed'].includes(
            normalizedStatus,
        )
    ) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800';
    }

    if (
        [
            'awaiting-approval',
            'payout-pending',
            'pending',
            'processing',
            'review',
        ].includes(normalizedStatus)
    ) {
        return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800';
    }

    if (
        [
            'payout-rejected',
            'expired',
            'failed',
        ].includes(normalizedStatus)
    ) {
        return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-200 dark:ring-rose-800';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700';
}

function actionIcon(
    action: CockpitPayCodeRowAction,
): 'distribution' | 'inspect' {
    return action.key.toLowerCase().includes('distribution')
        ? 'distribution'
        : 'inspect';
}

function visibleInstructionBadges(record: CockpitPayCodeExplorerRecord) {
    return record.instructionBadges;
}

function capabilityBadgeClass(capabilityKey: string): string {
    if (capabilityKey === 'collection') {
        return 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-800';
    }

    if (capabilityKey === 'settlement') {
        return 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950 dark:text-violet-200 dark:ring-violet-800';
    }

    return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800';
}

function hasClaimSummary(record: CockpitPayCodeExplorerRecord): boolean {
    return claimedAt(record) !== null;
}

function claimedAt(record: CockpitPayCodeExplorerRecord): string | null {
    return record.claimSummary?.claimed_at ?? record.timing.redeemedAt;
}

function claimedBy(record: CockpitPayCodeExplorerRecord): string {
    return (
        record.claimSummary?.claimed_by_label ??
        record.claimSummary?.claimed_mobile_masked ??
        record.party.primary
    );
}

function formatClaimAmount(record: CockpitPayCodeExplorerRecord): string | null {
    if (typeof record.claimSummary?.amount_minor !== 'number') {
        return null;
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: record.claimSummary?.currency ?? 'PHP',
        minimumFractionDigits: 2,
    }).format(record.claimSummary.amount_minor / 100);
}

function timingFacts(record: CockpitPayCodeExplorerRecord) {
    const claimTime = claimedAt(record);
    const terminalLabel = record.status.toLowerCase().includes('cancel')
        ? 'Cancelled'
        : record.status.toLowerCase().includes('expir')
          ? 'Expired'
          : 'Closed';

    const facts: Array<{ label: string; value: string | null }> = [
        { label: 'Created', value: record.timing.createdAt },
        { label: 'Claimed', value: claimTime },
    ];

    if (!claimTime) {
        facts.push({
            label: record.operationalStatus.isTerminal
                ? terminalLabel
                : 'Expires',
            value: record.operationalStatus.isTerminal
                ? record.timing.terminalAt
                : record.timing.expiresAt,
        });
    }

    return facts
        .filter((fact): fact is { label: string; value: string } => !!fact.value)
        .map((fact) => ({
            ...fact,
            relative: formatRelativeTime(fact.value, explorerNow.value),
            absolute: formatAbsoluteTime(fact.value),
        }));
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-pay-code-results-table"
    >
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        Results
                    </p>
                    <h3
                        class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                    >
                        Pay Code results
                    </h3>
                </div>
                <div
                    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center"
                    data-testid="cockpit-pay-code-results-density-summary"
                >
                    <p
                        class="text-sm font-medium text-slate-600 dark:text-slate-300"
                    >
                        <span
                            class="font-semibold text-slate-950 tabular-nums dark:text-slate-50"
                        >
                            {{ records.length }}
                        </span>
                        Pay Codes
                        <span aria-hidden="true">·</span>
                        Showing
                        <span class="font-mono tabular-nums">
                            {{ firstVisibleRecordNumber }}–{{
                                lastVisibleRecordNumber
                            }}
                            of
                            {{ records.length }}
                        </span>
                    </p>
                    <label
                        class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        for="cockpit-pay-code-page-size"
                    >
                        Rows
                        <select
                            id="cockpit-pay-code-page-size"
                            v-model.number="selectedVisibleLimit"
                            class="h-9 rounded-full border border-slate-200 bg-white px-3 text-sm font-semibold tracking-normal text-slate-700 normal-case shadow-sm transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-emerald-500 dark:focus:ring-emerald-900/60"
                            data-testid="cockpit-pay-code-result-page-size"
                        >
                            <option
                                v-for="option in visibleRecordLimitOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }} per page
                            </option>
                        </select>
                    </label>
                </div>
            </div>
        </div>

        <div
            class="divide-y divide-slate-100 md:hidden dark:divide-slate-800"
            data-testid="cockpit-pay-code-mobile-results"
        >
            <article
                v-for="record in visibleRecords"
                :key="`mobile-${record.code}`"
                :class="
                    record.attention ? 'bg-rose-50/60 dark:bg-rose-950/20' : ''
                "
                class="space-y-3 px-4 py-3"
                data-testid="cockpit-pay-code-mobile-row"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="font-mono text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ record.code }}
                        </p>
                        <p
                            class="mt-1 truncate text-sm text-slate-600 dark:text-slate-300"
                        >
                            {{ record.template }}
                        </p>
                        <span
                            :class="capabilityBadgeClass(record.capability.key)"
                            class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[0.65rem] font-semibold ring-1"
                            data-testid="cockpit-pay-code-mobile-capability"
                        >
                            {{ record.capability.label }} ·
                            {{ record.capability.voucherTypeLabel }}
                        </span>
                    </div>
                    <div class="shrink-0 text-right">
                        <span
                            :class="statusBadgeClass(record.status)"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                            data-testid="cockpit-pay-code-mobile-status-badge"
                        >
                            {{ statusLabel(record) }}
                        </span>
                        <p
                            class="mt-1.5 text-right font-mono text-sm font-semibold text-slate-950 tabular-nums dark:text-slate-50"
                            data-testid="cockpit-pay-code-mobile-amount"
                        >
                            {{ record.amount }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="record.attention"
                    class="flex items-start gap-2 rounded-lg border border-rose-200 bg-white/70 px-3 py-2 text-rose-800 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-200"
                    data-testid="cockpit-pay-code-mobile-attention"
                >
                    <CircleAlert
                        aria-hidden="true"
                        class="mt-0.5 size-4 shrink-0"
                    />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold">
                            {{ record.attention.label }}
                        </p>
                        <p class="truncate text-[0.7rem]">
                            {{ record.attention.message }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex min-h-6 flex-wrap items-center gap-1.5"
                    data-testid="cockpit-pay-code-mobile-instructions"
                >
                    <span
                        v-for="badge in visibleInstructionBadges(record)"
                        :key="badge.key"
                        class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-medium text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                    >
                        {{ badge.label }}
                    </span>
                    <span
                        v-if="record.instructionBadges.length === 0"
                        class="text-xs text-slate-400 dark:text-slate-500"
                    >
                        Standard instructions
                    </span>
                </div>

                <p
                    v-if="record.posReference?.sale_reference"
                    class="truncate font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                    :title="String(record.posReference?.sale_reference)"
                    data-testid="cockpit-pay-code-mobile-sale-reference"
                >
                    {{ record.posReference?.sale_reference }}
                </p>

                <p
                    v-if="record.purpose"
                    class="truncate text-xs text-slate-600 dark:text-slate-300"
                    :title="record.purpose"
                    data-testid="cockpit-pay-code-mobile-purpose"
                >
                    {{ record.purpose }}
                </p>

                <div
                    class="flex flex-wrap gap-x-3 gap-y-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-pay-code-mobile-timing"
                >
                    <span
                        v-for="fact in timingFacts(record)"
                        :key="`${record.code}-${fact.label}`"
                        :title="fact.absolute"
                    >{{ fact.label }} {{ fact.relative }}</span>
                </div>

                <div
                    v-if="hasClaimSummary(record)"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"
                    data-testid="cockpit-pay-code-mobile-claim-summary"
                >
                    <p class="font-semibold">
                        Claimed {{ formatRelativeTime(claimedAt(record) ?? '', explorerNow) }}
                    </p>
                    <p class="mt-0.5 truncate">
                        By {{ claimedBy(record) }}
                        <span v-if="formatClaimAmount(record)">
                            · {{ formatClaimAmount(record) }}
                        </span>
                        <span v-if="record.claimSummary.location_label">
                            · {{ record.claimSummary.location_label }}
                        </span>
                    </p>
                </div>

                <dl
                    class="rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-950"
                    data-testid="cockpit-pay-code-mobile-party"
                >
                    <dt
                        class="text-[0.65rem] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ record.party.label }}
                    </dt>
                    <dd
                        class="mt-0.5 truncate font-medium text-slate-800 dark:text-slate-100"
                    >
                        {{ record.party.primary }}
                    </dd>
                    <dd
                        v-if="record.party.secondary"
                        class="mt-0.5 truncate text-slate-500 dark:text-slate-400"
                    >
                        {{ record.party.secondary }}
                    </dd>
                </dl>

                <div class="flex flex-wrap items-center gap-2">
                    <CockpitPayCodeCopyButton :code="record.code" />
                    <CockpitPayCodeQuickTerminalAction
                        :code="record.code"
                        action="expire"
                        :enabled="record.terminalControl.canExpire"
                        :blocked-reason="record.terminalControl.blockedReason"
                    />
                    <CockpitPayCodeQuickTerminalAction
                        :code="record.code"
                        action="cancel"
                        :enabled="record.terminalControl.canCancel"
                        :blocked-reason="record.terminalControl.blockedReason"
                    />
                    <Link
                        v-for="action in enabledActions(record)"
                        :key="action.key"
                        :href="action.href ?? '#'"
                        :title="action.reason ?? undefined"
                        class="inline-flex min-h-9 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-center text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:border-emerald-700"
                        data-testid="cockpit-pay-code-mobile-row-action-link"
                    >
                        {{ action.label }}
                    </Link>
                    <details
                        class="group w-full"
                        data-testid="cockpit-pay-code-mobile-row-unavailable-actions"
                    >
                        <summary
                            class="inline-flex min-h-9 w-full cursor-pointer items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-center text-xs font-medium text-slate-500 transition hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                            data-testid="cockpit-pay-code-mobile-row-disabled-summary"
                        >
                            More
                            <span class="sr-only">
                                —
                                {{ disabledActions(record).length }} unavailable
                                actions
                            </span>
                        </summary>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <dl
                                class="grid w-full gap-2 rounded-lg bg-slate-50 p-2 text-left text-xs dark:bg-slate-900"
                                data-testid="cockpit-pay-code-mobile-row-secondary-facts"
                            >
                                <div>
                                    <dt
                                        class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Owner
                                    </dt>
                                    <dd
                                        class="mt-0.5 text-slate-700 dark:text-slate-200"
                                    >
                                        {{ record.owner }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Last Activity
                                    </dt>
                                    <dd
                                        class="mt-0.5 text-slate-700 dark:text-slate-200"
                                    >
                                        {{ record.lastActivity }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Created
                                    </dt>
                                    <dd
                                        class="mt-0.5 text-slate-700 dark:text-slate-200"
                                    >
                                        {{ record.timing.createdAt ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ hasClaimSummary(record) ? 'Claimed' : 'Expires' }}
                                    </dt>
                                    <dd
                                        class="mt-0.5 text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            claimedAt(record) ??
                                            record.timing.expiresAt ??
                                            '—'
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <button
                                v-for="action in disabledActions(record)"
                                :key="action.key"
                                :disabled="action.disabled !== false"
                                :title="action.reason ?? undefined"
                                type="button"
                                class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                                data-testid="cockpit-pay-code-mobile-row-action-disabled"
                            >
                                {{ action.label }}
                            </button>
                        </div>
                    </details>
                </div>
            </article>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table
                class="w-full min-w-[68rem] divide-y divide-slate-200 text-left text-sm dark:divide-slate-800"
            >
                <thead
                    class="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950 dark:text-slate-400"
                >
                    <tr>
                        <th class="w-[21%] px-4 py-2">Pay Code</th>
                        <th class="w-[23%] px-4 py-2">Instructions</th>
                        <th class="w-[13%] px-4 py-2 text-right">Amount</th>
                        <th class="w-[13%] px-4 py-2">Status</th>
                        <th class="w-[20%] px-4 py-2">Target / Claimed by</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="record in visibleRecords"
                        :key="record.code"
                        :class="
                            record.attention
                                ? 'bg-rose-50/50 dark:bg-rose-950/15'
                                : ''
                        "
                        data-testid="cockpit-pay-code-row"
                    >
                        <td
                            class="min-w-0 px-4 py-2.5"
                            data-testid="cockpit-pay-code-row-identity"
                        >
                            <p
                                v-if="record.posReference?.sale_reference"
                                class="mt-0.5 truncate font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                                :title="String(record.posReference?.sale_reference)"
                                data-testid="cockpit-pay-code-sale-reference"
                            >
                                {{ record.posReference?.sale_reference }}
                            </p>
                            <p
                                class="truncate font-mono font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ record.code }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ record.template }}
                            </p>
                            <p
                                v-if="record.purpose"
                                class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"
                                :title="record.purpose"
                                data-testid="cockpit-pay-code-purpose"
                            >
                                {{ record.purpose }}
                            </p>
                            <div
                                class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[0.65rem] text-slate-400 dark:text-slate-500"
                                data-testid="cockpit-pay-code-timing"
                            >
                                <span
                                    v-for="fact in timingFacts(record)"
                                    :key="`${record.code}-${fact.label}`"
                                    :title="fact.absolute"
                                >{{ fact.label }} {{ fact.relative }}</span>
                            </div>
                            <span
                                :class="
                                    capabilityBadgeClass(record.capability.key)
                                "
                                class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[0.65rem] font-semibold ring-1"
                                data-testid="cockpit-pay-code-capability"
                            >
                                {{ record.capability.label }} ·
                                {{ record.capability.voucherTypeLabel }}
                            </span>
                        </td>
                        <td
                            class="px-4 py-2.5 align-top"
                            data-testid="cockpit-pay-code-instructions"
                        >
                            <div class="flex min-h-6 flex-wrap gap-1.5">
                                <span
                                    v-for="badge in visibleInstructionBadges(
                                        record,
                                    )"
                                    :key="badge.key"
                                    class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-medium text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700"
                                >
                                    {{ badge.label }}
                                </span>
                                <span
                                    v-if="record.instructionBadges.length === 0"
                                    class="text-xs text-slate-400 dark:text-slate-500"
                                >
                                    Standard
                                </span>
                            </div>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right font-mono text-slate-700 tabular-nums dark:text-slate-200"
                            data-testid="cockpit-pay-code-amount"
                        >
                            {{ record.amount }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                :class="statusBadgeClass(record.status)"
                                class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                data-testid="cockpit-pay-code-status-badge"
                            >
                                {{ statusLabel(record) }}
                            </span>
                            <div
                                v-if="record.attention"
                                class="mt-2 flex max-w-40 items-start gap-1.5 text-rose-700 dark:text-rose-300"
                                data-testid="cockpit-pay-code-row-attention"
                                :title="record.attention.message"
                            >
                                <CircleAlert
                                    aria-hidden="true"
                                    class="mt-0.5 size-3.5 shrink-0"
                                />
                                <span
                                    class="text-[0.7rem] font-semibold leading-4"
                                >
                                    {{ record.attention.label }}
                                </span>
                            </div>
                        </td>
                        <td
                            class="px-4 py-2.5 align-top"
                            data-testid="cockpit-pay-code-party"
                        >
                            <p
                                class="text-[0.65rem] font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500"
                            >
                                {{ record.party.label }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-sm font-medium text-slate-800 dark:text-slate-100"
                            >
                                {{ record.party.primary }}
                            </p>
                            <p
                                v-if="record.party.secondary"
                                class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ record.party.secondary }}
                            </p>
                            <div
                                v-if="hasClaimSummary(record)"
                                class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-[0.7rem] leading-4 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"
                                data-testid="cockpit-pay-code-claim-summary"
                            >
                                <p class="font-semibold">
                                    Claimed {{ formatAbsoluteTime(claimedAt(record) ?? '') }}
                                </p>
                                <p class="truncate">
                                    By {{ claimedBy(record) }}
                                    <span v-if="formatClaimAmount(record)">
                                        · {{ formatClaimAmount(record) }}
                                    </span>
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex justify-end gap-1.5">
                                <CockpitPayCodeCopyButton :code="record.code" compact />
                                <CockpitPayCodeQuickTerminalAction
                                    :code="record.code"
                                    action="expire"
                                    :enabled="record.terminalControl.canExpire"
                                    :blocked-reason="record.terminalControl.blockedReason"
                                />
                                <CockpitPayCodeQuickTerminalAction
                                    :code="record.code"
                                    action="cancel"
                                    :enabled="record.terminalControl.canCancel"
                                    :blocked-reason="record.terminalControl.blockedReason"
                                />
                                <div class="flex gap-1.5">
                                    <Link
                                        v-for="action in enabledActions(record)"
                                        :key="action.key"
                                        :href="action.href ?? '#'"
                                        :aria-label="action.label"
                                        :title="action.label"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:border-emerald-700"
                                        data-testid="cockpit-pay-code-row-action-link"
                                    >
                                        <Share2
                                            v-if="
                                                actionIcon(action) ===
                                                'distribution'
                                            "
                                            aria-hidden="true"
                                            class="h-4 w-4"
                                        />
                                        <Eye
                                            v-else
                                            aria-hidden="true"
                                            class="h-4 w-4"
                                        />
                                        <span class="sr-only">{{
                                            action.label
                                        }}</span>
                                    </Link>
                                </div>
                                <details
                                    class="group text-xs text-slate-500 dark:text-slate-400"
                                    data-testid="cockpit-pay-code-row-unavailable-actions"
                                >
                                    <summary
                                        aria-label="More row actions"
                                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                                        title="More row actions"
                                    >
                                        <MoreHorizontal
                                            aria-hidden="true"
                                            class="h-4 w-4"
                                        />
                                        <span class="sr-only">
                                            —
                                            {{ disabledActions(record).length }}
                                            unavailable actions
                                        </span>
                                    </summary>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <dl
                                            class="grid w-full gap-2 rounded-lg bg-slate-50 p-2 text-left text-xs dark:bg-slate-900"
                                            data-testid="cockpit-pay-code-row-secondary-facts"
                                        >
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Owner
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{ record.owner }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Last Activity
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{ record.lastActivity }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Created
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{
                                                        record.timing
                                                            .createdAt ?? '—'
                                                    }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Starts
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{
                                                        record.timing
                                                            .startsAt ?? '—'
                                                    }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    {{ hasClaimSummary(record) ? 'Claimed' : 'Expires' }}
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{
                                                        claimedAt(record) ??
                                                        record.timing
                                                            .expiresAt ??
                                                        '—'
                                                    }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt
                                                    class="font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    Redeemed
                                                </dt>
                                                <dd
                                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                                >
                                                    {{
                                                        record.timing
                                                            .redeemedAt ?? '—'
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <button
                                            v-for="action in disabledActions(
                                                record,
                                            )"
                                            :key="action.key"
                                            :disabled="
                                                action.disabled !== false
                                            "
                                            :title="action.reason ?? undefined"
                                            type="button"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                                            data-testid="cockpit-pay-code-row-action-disabled"
                                        >
                                            {{ action.label }}
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="isResultLimited"
            aria-label="Pay Code result pages footer"
            class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950"
            data-testid="cockpit-pay-code-result-pagination-footer"
        >
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                Showing {{ firstVisibleRecordNumber }}–{{
                    lastVisibleRecordNumber
                }}
                of {{ records.length }} · Page {{ currentPage }} of
                {{ totalPages }}
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    :disabled="!hasPreviousPage"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                    data-testid="cockpit-pay-code-result-pagination-footer-previous"
                    @click="goToPreviousPage"
                >
                    <ChevronLeft aria-hidden="true" class="size-4" />
                    Previous
                </button>
                <button
                    :disabled="!hasNextPage"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                    data-testid="cockpit-pay-code-result-pagination-footer-next"
                    @click="goToNextPage"
                >
                    Next
                    <ChevronRight aria-hidden="true" class="size-4" />
                </button>
            </div>
        </nav>

        <div
            v-if="records.length === 0"
            class="border-t border-slate-200 p-5 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
            data-testid="cockpit-pay-code-empty-state"
        >
            No Pay Codes available in the sanitized read model.
        </div>
    </section>
</template>
