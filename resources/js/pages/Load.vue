<!-- resources/js/pages/Load.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { useQrCode } from '@/composables/useQrCode';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Load', href: '/load' },
];

const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } = useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
const account = usePage().props.auth.user.mobile;
const amount = 0;
const { qrCode, status, message, refresh } = useQrCode(account, amount);
</script>

<template>
    <Head title="Load" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto mt-2 max-w-xs transform scale-75 origin-top">
            <WalletBalanceDisplay
                :balance="balance"
                :currency="currency"
                :type="walletType"
                :status="balStatus"
                :message="balMessage"
                :refresh="fetchBalance"
            />
        </div>
        <div class="p-4">
            <p v-if="status === 'loading'">{{ message }}</p>
            <QrDisplay :qr-code="qrCode" />
            <button @click="refresh">Regenerate</button>
        </div>
    </AppLayout>
</template>
