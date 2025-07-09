<!-- resources/js/pages/Generate.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import CutCheck from '@/components/domain/CutCheck.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Generate', href: '/generate' },
];
const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } = useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
</script>

<template>
    <Head title="Generate" />

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
            <CutCheck/>
        </div>
    </AppLayout>
</template>
