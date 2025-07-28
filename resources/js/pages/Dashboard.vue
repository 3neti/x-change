<script setup lang="ts">
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem, VoucherList } from '@/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Wallet, Tickets, Sigma } from 'lucide-vue-next';
import RecentRedemptions from '@/components/domain/RecentRedemptions.vue';
import { computed } from 'vue';
import { useFormatCurrency } from '@/composables/useFormatCurrency';

export interface Totals {
    total: {
        amount: number;
        currency: string;
    };
    count: number;
    latest_created_at: string | null;
}
const props = defineProps<{
    vouchers: VoucherList;
    totalRedeemables: Record<string, Totals>,
    totalRedeemed: Record<string, Totals>;
}>();

const { formattedBalance, updatedAt } = useWalletBalance('platform');
const voucherCount = computed(() => props.vouchers.length);
const formatCurrency = useFormatCurrency();
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">
                                Wallet Balance
                            </CardTitle>
                            <Wallet />
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center">
                                <div class="text-2xl font-bold">
                                    {{ formattedBalance }}
                                </div>
                                <p class="text-xs">
                                    <span class="text-muted-foreground">last updated </span>
                                    <span> {{ updatedAt }} </span>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">
                                Total Disbursed
                            </CardTitle>
                            <Sigma />
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center">
                                <div class="text-2xl font-bold">
                                    {{ formatCurrency(totalRedeemed?.PHP?.total?.amount || 0) }}
                                </div>
                                <p class="text-xs">
                                    <span class="text-muted-foreground">as of </span>
                                    <span> {{ totalRedeemed?.PHP?.latest_created_at }}</span>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">
                                Outstanding Vouchers
                            </CardTitle>
                            <Tickets />
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center">
                                <div class="text-2xl font-bold">
                                    {{ totalRedeemables?.PHP?.count || 0 }}
                                </div>
                                <p class="text-xs">
                                    <span class="text-muted-foreground">totalling </span>
                                    <span> {{ formatCurrency(totalRedeemables?.PHP?.total?.amount || 0) }} </span>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
                <Card class="col-span-3">
                    <CardHeader>
                        <CardTitle>Recent Redemptions</CardTitle>
                        <CardDescription>
                            You have {{ voucherCount }} codes redeemed this month.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RecentRedemptions :vouchers="props.vouchers" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
