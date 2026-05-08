<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { RefreshCw, Clock, AlertTriangle } from 'lucide-vue-next';

interface BalanceData {
    balance: number;
    available_balance?: number;
    currency: string;
    checked_at?: string;
    is_low?: boolean;
    label?: string;
}

const props = withDefaults(
    defineProps<{
        data?: BalanceData;
    }>(),
    {
        data: undefined,
    },
);

const refreshing = ref(false);

const formattedBalance = computed(() => {
    if (!props.data) return 'N/A';

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.data.currency || 'PHP',
    }).format(props.data.balance);
});

const lastChecked = computed(() => {
    if (!props.data?.checked_at) return 'Never';

    const date = new Date(props.data.checked_at);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;

    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                {{ data?.label ?? 'Balance' }}
                <AlertTriangle
                    v-if="data?.is_low"
                    class="h-5 w-5 text-destructive"
                />
            </CardTitle>
            <CardDescription v-if="!data"
                >No balance data available</CardDescription
            >
        </CardHeader>
        <CardContent>
            <div v-if="data" class="space-y-4">
                <div>
                    <p class="text-sm text-muted-foreground">Current Balance</p>
                    <p
                        class="text-3xl font-bold"
                        :class="{ 'text-destructive': data.is_low }"
                    >
                        {{ formattedBalance }}
                    </p>
                </div>

                <div
                    v-if="data.checked_at"
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Clock class="h-4 w-4" />
                    <span>Updated {{ lastChecked }}</span>
                </div>

                <div
                    v-if="data.is_low"
                    class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                >
                    <AlertTriangle class="mr-2 inline h-4 w-4" />
                    Balance is below threshold
                </div>
            </div>

            <div
                v-else
                class="space-y-4 py-8 text-center text-muted-foreground"
            >
                <p class="text-lg font-medium">No balance data available</p>
                <p class="mt-2 text-sm">
                    Balance data will appear once wallets are configured.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
