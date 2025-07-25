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
const { formattedBalance } = useWalletBalance();
// const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } = useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
const account = usePage().props.auth.user.mobile;
const amount = 0;
const { qrCode, status, message, refresh } = useQrCode(account, amount);
</script>

<template>
    <Head title="Load" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-8 max-w-xl mx-auto space-y-6 bg-white rounded-xl shadow-lg">
            <p v-if="status === 'loading'" class="text-base text-gray-600 italic">{{ message }}</p>

            <!-- Heading -->
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-700 uppercase tracking-widest">
                    Scan to Load
                </h2>
            </div>

            <!-- QR Code Display -->
            <div class="flex justify-center">
                <div class="w-72 h-72">
                    <QrDisplay :qr-code="qrCode" class="w-full h-full" />
                </div>
            </div>

            <!-- Balance Info -->
            <div class="text-sm space-y-1">
                <div class="flex justify-between text-gray-600 italic">
                    <span>Balance:</span>
                    <span class="font-semibold text-gray-800 text-right w-24">
                    {{ formattedBalance }}
                </span>
                </div>
            </div>

            <!-- Action Button -->
            <div class="text-center">
                <button
                    @click="refresh"
                    class="mt-4 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-base font-medium rounded-md shadow transition"
                >
                    🔄 Regenerate QR Code
                </button>
            </div>
        </div>
    </AppLayout>
</template>
