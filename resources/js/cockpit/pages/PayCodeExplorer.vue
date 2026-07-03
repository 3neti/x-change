<script setup lang="ts">
import { computed } from 'vue';
import CockpitPayCodeFilterBuilder from '../components/CockpitPayCodeFilterBuilder.vue';
import CockpitPayCodeResultsTable from '../components/CockpitPayCodeResultsTable.vue';
import CockpitPayCodeSearchBar from '../components/CockpitPayCodeSearchBar.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitPayCodeExplorerFilter,
    CockpitPayCodeExplorerPageProps,
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeExplorerReadModelRecord,
} from '../types';
import {
    cockpitPayCodeExplorerFilters,
    cockpitPayCodeExplorerRecords,
    cockpitPayCodeRowActions,
} from '../payCodeExplorerDefaults';

const props = defineProps<CockpitPayCodeExplorerPageProps>();

const readModel = computed(() => props.pay_codes_read_model);
const isHydrated = computed(() => readModel.value?.authorized === true);
const query = computed(() => stringValue(readModel.value?.query) ?? '');
const payloadPolicy = computed(() => stringValue(readModel.value?.redactions?.payloads) ?? 'not-loaded');
const status = computed(() => stringValue(readModel.value?.status) ?? 'not_wired');

const filters = computed<CockpitPayCodeExplorerFilter[]>(() => {
    if (!isHydrated.value) {
        return cockpitPayCodeExplorerFilters;
    }

    return [
        {
            key: 'query',
            label: 'Query',
            value: query.value || 'All sanitized records',
            helper: 'Filtering remains local and read-only until an approved host query API exists.',
        },
        {
            key: 'payload-policy',
            label: 'Payload policy',
            value: payloadPolicy.value,
            helper: 'Only sanitized list fields are rendered in this slice.',
        },
        {
            key: 'readiness',
            label: 'Read model status',
            value: status.value,
            helper: 'This status describes list-readiness only; it is not voucher lifecycle truth.',
        },
    ];
});

const records = computed<CockpitPayCodeExplorerRecord[]>(() => {
    if (!isHydrated.value) {
        return cockpitPayCodeExplorerRecords;
    }

    return (readModel.value?.records ?? [])
        .map((record) => sanitizeRecord(record))
        .filter((record): record is CockpitPayCodeExplorerRecord => record !== null);
});

function sanitizeRecord(record: CockpitPayCodeExplorerReadModelRecord): CockpitPayCodeExplorerRecord | null {
    const code = stringValue(record.code);

    if (code === null) {
        return null;
    }

    return {
        code,
        template: stringValue(record.template) ?? 'Template pending',
        amount: moneyValue(record.amount, stringValue(record.currency)),
        status: stringValue(record.display_status) ?? stringValue(record.status) ?? 'not_wired',
        owner: stringValue(record.owner) ?? 'Redacted',
        lastActivity: stringValue(record.last_activity) ?? 'Read model activity pending',
    };
}

function stringValue(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function moneyValue(value: unknown, currency: string | null = 'PHP'): string {
    if (typeof value === 'string' && value.trim() !== '' && Number.isNaN(Number(value))) {
        return value.trim();
    }

    const amount = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(amount)) {
        return 'Pending';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency ?? 'PHP',
    }).format(amount);
}
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-pay-code-explorer-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 13
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Pay Code Explorer Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Pay Code Explorer Read Model hydration renders sanitized list facts when supplied.
                    This screen remains read-only: it does not mutate vouchers, execute drivers, approve
                    claims, send feedback, write journal entries, call providers, or move money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Read model
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ status }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Records
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ records.length }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload policy
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ payloadPolicy }}
                        </dd>
                    </div>
                </dl>
            </div>

            <CockpitPayCodeSearchBar :query="query" />
            <CockpitPayCodeFilterBuilder :filters="filters" />
            <CockpitPayCodeResultsTable
                :records="records"
                :actions="cockpitPayCodeRowActions"
            />
        </section>
    </CockpitLayout>
</template>
