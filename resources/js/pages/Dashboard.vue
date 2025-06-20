<script setup lang="ts">
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import { useQrCode } from '@/composables/useQrCode';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import GenerateToken from '@/components/domain/GenerateToken.vue';
import CutCheck from '@/components/domain/CutCheck.vue';

const user = usePage().props.auth.user;
console.log(user.mobile);
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const account = user.mobile;
const amount = 0;
const { qrCode, status, message, refresh } = useQrCode(account, amount);

const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } = useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <div>
                        <p v-if="status === 'loading'">{{ message }}</p>
                        <QrDisplay :qr-code="qrCode" />
                        <button @click="refresh">Regenerate</button>
                    </div>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <!-- shrink the entire WalletBalanceDisplay to ~75% of its normal size -->
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
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <GenerateToken />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
               <CutCheck/>
            </div>
        </div>
    </AppLayout>
</template>
