<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Banknote, CheckCircle2, Clock, XCircle } from 'lucide-vue-next';

interface Stats {
    total?: number;
    active?: number;
    redeemed?: number;
    expired?: number;
    total_amount?: string | number;
    redeemed_amount?: string | number;
}

interface Props {
    stats: Stats;
}

defineProps<Props>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Pay Codes</CardTitle>
                <Banknote class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.total ?? 0 }}</div>
                <p class="text-xs text-muted-foreground">Generated vouchers</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Active</CardTitle>
                <Clock class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.active ?? 0 }}</div>
                <p class="text-xs text-muted-foreground">Available to claim</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Redeemed</CardTitle>
                <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.redeemed ?? 0 }}</div>
                <p class="text-xs text-muted-foreground">Successfully claimed</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Expired</CardTitle>
                <XCircle class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.expired ?? 0 }}</div>
                <p class="text-xs text-muted-foreground">No longer claimable</p>
            </CardContent>
        </Card>
    </div>
</template>
