<script setup lang="ts">
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import { useQrCode } from '@/composables/useQrCode';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

const user = usePage().props.auth.user;

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
                    <PlaceholderPattern/>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <PlaceholderPattern/>
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                    <PlaceholderPattern/>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
                <PlaceholderPattern/>
            </div>
        </div>
    </AppLayout>
</template>
