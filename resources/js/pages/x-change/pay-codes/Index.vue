<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Plus } from 'lucide-vue-next';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Pay Codes', href: '/x/pay-codes' },
        ],
    },
});

interface VoucherSummary {
    id: number;
    code: string;
    amount: number;
    currency: string;
    status: string;
}

const vouchers = ref<VoucherSummary[]>([]);
const loading = ref(true);

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'redeemed':
            return 'secondary';
        case 'cancelled':
        case 'expired':
            return 'destructive';
        default:
            return 'outline';
    }
};

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

onMounted(async () => {
    try {
        const response = await fetch('/api/x/v1/vouchers', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await response.json();
        vouchers.value = json.data?.items ?? json.data ?? [];
    } catch {
        // silently fail
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Pay Codes" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Pay Codes</h2>
            <Button as-child>
                <Link href="/x/pay-codes/create">
                    <Plus class="mr-2 h-4 w-4" />
                    Create Pay Code
                </Link>
            </Button>
        </div>

        <Card>
            <CardContent class="p-0">
                <div v-if="loading" class="space-y-3 p-6">
                    <Skeleton v-for="i in 5" :key="i" class="h-12 w-full" />
                </div>

                <div
                    v-else-if="vouchers.length === 0"
                    class="py-12 text-center text-muted-foreground"
                >
                    <p>No pay codes yet.</p>
                    <Button as-child class="mt-4" variant="outline">
                        <Link href="/x/pay-codes/create">Create your first Pay Code</Link>
                    </Button>
                </div>

                <div v-else class="divide-y">
                    <Link
                        v-for="voucher in vouchers"
                        :key="voucher.id"
                        :href="`/x/pay-codes/${voucher.code}`"
                        class="flex items-center justify-between px-6 py-4 hover:bg-muted/50 transition-colors"
                    >
                        <div>
                            <p class="font-mono text-sm font-semibold">
                                {{ voucher.code }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatAmount(voucher.amount, voucher.currency) }}
                            </p>
                        </div>
                        <Badge :variant="getStatusVariant(voucher.status)">
                            {{ voucher.status }}
                        </Badge>
                    </Link>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
