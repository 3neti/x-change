<script setup lang="ts">
import CockpitPayCodeFilterBuilder from '../components/CockpitPayCodeFilterBuilder.vue';
import CockpitPayCodeResultsTable from '../components/CockpitPayCodeResultsTable.vue';
import CockpitPayCodeSearchBar from '../components/CockpitPayCodeSearchBar.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import {
    cockpitPayCodeExplorerFilters,
    cockpitPayCodeExplorerRecords,
    cockpitPayCodeRowActions,
} from '../payCodeExplorerDefaults';
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-pay-code-explorer-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 4
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Pay Code Explorer Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen establishes read-only Pay Code exploration. It does not mutate vouchers,
                    execute drivers, approve claims, send feedback, write journal entries, call providers,
                    or move money.
                </p>
            </div>

            <CockpitPayCodeSearchBar />
            <CockpitPayCodeFilterBuilder :filters="cockpitPayCodeExplorerFilters" />
            <CockpitPayCodeResultsTable
                :records="cockpitPayCodeExplorerRecords"
                :actions="cockpitPayCodeRowActions"
            />
        </section>
    </CockpitLayout>
</template>

