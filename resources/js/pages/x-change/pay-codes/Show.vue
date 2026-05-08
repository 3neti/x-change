<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Pay Codes', href: '/x/pay-codes' },
            { title: 'Detail', href: '#' },
        ],
    },
});

interface VoucherDetail {
    id: number;
    code: string;
    amount: number;
    currency: string;
    status: string;
    claimed: boolean;
    fully_claimed: boolean;
    issuer_id?: number;
}

const props = defineProps<{
    voucher: VoucherDetail;
}>();

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

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
</script>

<template>
    <Head :title="`Pay Code ${voucher.code}`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card class="mx-auto w-full max-w-2xl">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="font-mono text-xl">
                        {{ voucher.code }}
                    </CardTitle>
                    <Badge :variant="getStatusVariant(voucher.status)">
                        {{ voucher.status }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Amount</dt>
                        <dd class="text-lg font-semibold">
                            {{ formatAmount(voucher.amount, voucher.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Currency</dt>
                        <dd>{{ voucher.currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Claimed</dt>
                        <dd>{{ voucher.claimed ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Fully Claimed</dt>
                        <dd>{{ voucher.fully_claimed ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
