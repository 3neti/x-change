<script setup lang="ts">

import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import { useQrCode } from '@/composables/useQrCode';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const amount = 150;
const { qrCode, status, message, refresh } = useQrCode(amount);

const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } =
    useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">

                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div>
                        <p v-if="status === 'loading'">{{ message }}</p>
                        <QrDisplay :qr-code="qrCode" />
                        <button @click="refresh">Regenerate</button>
                    </div>
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="max-w-md mx-auto mt-10">
                        <WalletBalanceDisplay
                            :balance="balance"
                            :currency="currency"
                            :type="walletType"
                            :status="balStatus"
                            :message="balMessage"
                            :refresh="fetchBalance"
                        />
                    </div>
<!--                    <PlaceholderPattern />-->
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
