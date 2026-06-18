<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import BalanceOverviewCards, {
    type BalanceOverview,
} from '@/components/x-change/BalanceOverviewCards.vue';
import ReconciliationStatusCard from '@/components/x-change/ReconciliationStatusCard.vue';
import {
    useXChangeDashboardApi,
    type DashboardStats,
} from '@/composables/useXChangeDashboardApi';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';

defineOptions({
    layout: [XChangeLayout, {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Balances', href: '/x/balances' },
        ],
    }],
});

const { getStats } = useXChangeDashboardApi();
defineProps<{
    balance_overview?: BalanceOverview | null;
}>();

const stats = ref<DashboardStats | null>(null);
const loading = ref(true);

onMounted(async () => {
    stats.value = await getStats();
    loading.value = false;
});
</script>

<template>
    <Head title="Balances" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold">Balances & Reconciliation</h2>
            <p class="text-sm text-muted-foreground">
                Balance authority changes by provider. Paynamics uses the provider wallet; NetBank uses the local ledger.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <BalanceOverviewCards :overview="balance_overview ?? null" />

            <ReconciliationStatusCard
                v-if="stats"
                :data="{
                    needs_review: stats.reconciliations.needs_review,
                    total_attempts: stats.disbursements.total_attempts,
                    success_rate: stats.disbursements.success_rate,
                }"
            />
        </div>
    </div>
</template>
