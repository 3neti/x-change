<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Ticket, TicketCheck, Ban, TrendingUp } from 'lucide-vue-next';
import StatCard from '@/components/x-change/StatCard.vue';
import QuickActions from '@/components/x-change/QuickActions.vue';
import RecentActivity from '@/components/x-change/RecentActivity.vue';
import {
    useXChangeDashboardApi,
    type DashboardStats,
    type RecentActivity as RecentActivityType,
} from '@/composables/useXChangeDashboardApi';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/x/dashboard',
            },
        ],
    },
});

const { loading, getStats, getActivity } = useXChangeDashboardApi();

const stats = ref<DashboardStats | null>(null);
const activity = ref<RecentActivityType | null>(null);
const statsLoading = ref(true);
const activityLoading = ref(true);

onMounted(async () => {
    const [s, a] = await Promise.all([
        getStats().finally(() => (statsLoading.value = false)),
        getActivity().finally(() => (activityLoading.value = false)),
    ]);
    stats.value = s;
    activity.value = a;
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Stats Grid -->
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <StatCard
                title="Total Pay Codes"
                :value="stats?.vouchers.total ?? '—'"
                :subtitle="`${stats?.vouchers.active ?? 0} active`"
                :icon="Ticket"
                :loading="statsLoading"
                href="/x/pay-codes"
            />
            <StatCard
                title="Redeemed"
                :value="stats?.vouchers.redeemed ?? '—'"
                :icon="TicketCheck"
                :loading="statsLoading"
            />
            <StatCard
                title="Disbursement Rate"
                :value="stats ? `${stats.disbursements.success_rate}%` : '—'"
                :subtitle="`${stats?.disbursements.successful ?? 0} / ${stats?.disbursements.total_attempts ?? 0}`"
                :icon="TrendingUp"
                :loading="statsLoading"
            />
            <StatCard
                title="Total Disbursed"
                :value="stats ? formatCurrency(stats.disbursements.total_disbursed) : '—'"
                :icon="Ban"
                :loading="statsLoading"
            />
        </div>

        <!-- Quick Actions + Activity -->
        <div class="grid gap-4 md:grid-cols-2">
            <QuickActions />
            <RecentActivity :activity="activity" :loading="activityLoading" />
        </div>
    </div>
</template>
