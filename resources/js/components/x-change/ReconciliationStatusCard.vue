<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    AlertTriangle,
    CheckCircle,
    AlertCircle,
} from 'lucide-vue-next';

interface ReconciliationSummary {
    needs_review: number;
    total_attempts?: number;
    success_rate?: number;
}

const props = defineProps<{
    data: ReconciliationSummary;
}>();

const statusConfig = computed(() => {
    if (props.data.needs_review > 0) {
        return {
            icon: AlertCircle,
            iconColor: 'text-yellow-600',
            bgColor: 'bg-yellow-50 dark:bg-yellow-950/20',
            borderColor: 'border-yellow-600/50',
            title: 'Needs Attention',
            variant: 'default' as const,
        };
    }

    return {
        icon: CheckCircle,
        iconColor: 'text-green-600',
        bgColor: 'bg-green-50 dark:bg-green-950/20',
        borderColor: 'border-green-600/50',
        title: 'All Clear',
        variant: 'default' as const,
    };
});
</script>

<template>
    <Card
        :class="[statusConfig.bgColor, statusConfig.borderColor, 'border-2']"
    >
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <component
                    :is="statusConfig.icon"
                    :class="[statusConfig.iconColor, 'h-5 w-5']"
                />
                Reconciliation: {{ statusConfig.title }}
            </CardTitle>
            <CardDescription>Disbursement reconciliation status</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Needs Review</span
                    >
                    <Badge
                        :variant="
                            data.needs_review > 0 ? 'destructive' : 'default'
                        "
                    >
                        {{ data.needs_review }}
                    </Badge>
                </div>

                <div
                    v-if="data.success_rate !== undefined"
                    class="flex items-center justify-between"
                >
                    <span class="text-sm text-muted-foreground"
                        >Success Rate</span
                    >
                    <span class="text-sm font-semibold"
                        >{{ data.success_rate }}%</span
                    >
                </div>

                <div
                    v-if="data.needs_review > 0"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    <p>
                        ⚠️ {{ data.needs_review }} disbursement(s) require
                        manual review.
                    </p>
                </div>
                <div v-else class="mt-2 text-sm text-muted-foreground">
                    <p>✅ All disbursements are reconciled.</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
