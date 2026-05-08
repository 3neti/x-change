<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type { RecentActivity } from '@/composables/useXChangeDashboardApi';
import { Ticket, TicketCheck, FileSearch } from 'lucide-vue-next';

interface Props {
    activity: RecentActivity | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusVariant = (status: string) => {
    switch (status?.toLowerCase()) {
        case 'active':
        case 'succeeded':
            return 'default';
        case 'redeemed':
        case 'completed':
            return 'secondary';
        case 'failed':
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
};

const allActivities = computed(() => {
    if (!props.activity) return [];

    const activities = [];

    if (props.activity.vouchers) {
        activities.push(
            ...props.activity.vouchers.map((v) => ({
                ...v,
                icon: Ticket,
                displayText: `Pay Code ${v.code}`,
                timestamp: v.created_at,
            })),
        );
    }

    if (props.activity.claims) {
        activities.push(
            ...props.activity.claims.map((c) => ({
                ...c,
                icon: TicketCheck,
                displayText: `Claim ${c.code ?? ''}${c.mobile ? ` → ${c.mobile}` : ''}`,
                timestamp: c.created_at,
            })),
        );
    }

    if (props.activity.reconciliations) {
        activities.push(
            ...props.activity.reconciliations.map((r) => ({
                ...r,
                icon: FileSearch,
                displayText: `Reconciliation ${r.reference ?? `#${r.id}`}`,
                timestamp: r.created_at,
            })),
        );
    }

    return activities.sort(
        (a, b) =>
            new Date(b.timestamp ?? 0).getTime() -
            new Date(a.timestamp ?? 0).getTime(),
    );
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-4">
                <Skeleton v-for="i in 5" :key="i" class="h-16 w-full" />
            </div>

            <div
                v-else-if="allActivities.length === 0"
                class="py-8 text-center"
            >
                <p class="text-sm text-muted-foreground">No recent activity</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="item in allActivities.slice(0, 10)"
                    :key="`${item.type}-${item.id}`"
                    class="flex items-center gap-3 rounded-lg border p-3"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                    >
                        <component
                            :is="item.icon"
                            class="h-4 w-4 text-muted-foreground"
                        />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            {{ item.displayText }}
                        </p>
                        <p
                            v-if="item.timestamp"
                            class="text-xs text-muted-foreground"
                        >
                            {{ formatDate(item.timestamp) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            v-if="item.amount"
                            class="text-sm font-semibold"
                        >
                            {{
                                formatAmount(
                                    item.amount,
                                    item.currency ?? 'PHP',
                                )
                            }}
                        </p>
                        <Badge
                            v-if="item.status"
                            :variant="getStatusVariant(item.status)"
                            class="text-xs"
                        >
                            {{ item.status }}
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
