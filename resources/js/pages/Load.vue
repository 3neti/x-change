<!-- resources/js/pages/Load.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { useQrCode } from '@/composables/useQrCode';
import QrDisplay from '@/components/domain/QrDisplay.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button';
import { cn } from "@/lib/utils"
import { RefreshCcw } from "lucide-vue-next"
import { useToast } from "@/components/ui/toast/use-toast"
import { watch } from 'vue';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Load', href: '/load' },
];
const { formattedBalance } = useWalletBalance();
// const { balance, currency, walletType, status: balStatus, message: balMessage, fetchBalance } = useWalletBalance(); // or pass a specific type: useWalletBalance('platform');
const account = usePage().props.auth.user.mobile;
const amount = 0;
const { qrCode, status, message, refresh } = useQrCode(account, amount);

const { toast } = useToast()

// whenever status becomes "loading", show a toast
watch(status, (newStatus) => {
    if (newStatus === 'loading') {
        toast({
            title: 'Please wait',
            description: message.value,
            // variant could be “default” or if your toast supports a loading style:
            // variant: 'loading',
            duration: 30_000,    // keep it visible until you manually dismiss
            // you can also expose an `onClose` callback to reset status if needed
        })
    }
    else if (newStatus === 'success') {
        toast({
            title: 'Done',
            description: message.value || 'Operation completed.',
            variant: 'default',
        })
    }
    else if (newStatus === 'error') {
        toast({
            title: 'Error',
            description: message.value || 'Something went wrong.',
            variant: 'destructive',  // or however you style errors
        })
    }
})
</script>

<template>
    <Head title="Load" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Card :class="cn('w-[380px]', $attrs.class ?? '')">
            <CardHeader>
                <CardTitle>Scan to Load</CardTitle>
                <CardDescription>Balance: {{ formattedBalance }}</CardDescription>
            </CardHeader>

            <CardContent class="grid place-items-center">
                <div class="w-72 h-72 flex items-center justify-center">
                    <QrDisplay :qr-code="qrCode" class="w-full h-full" />
                </div>
            </CardContent>

            <!-- Action Button -->
            <CardFooter>
                <Button class="w-full"
                    @click="refresh"
                >
                    <RefreshCcw/>
                    Regenerate
                </Button>
            </CardFooter>
        </Card>
<!--        <p v-if="status === 'loading'" class="text-base text-gray-600 italic">{{ message }}</p>-->
    </AppLayout>
</template>
