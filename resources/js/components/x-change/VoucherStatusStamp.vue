<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next';

interface Props {
    status: 'redeemed' | 'expired' | 'cancelled';
    statusDate?: string | null;
    voucherCode?: string | null;
    formattedAmount?: string | null;
}

const props = defineProps<Props>();

const statusConfig = computed(() => {
    switch (props.status) {
        case 'redeemed':
            return {
                label: 'Redeemed',
                stampColor: 'border-emerald-500/40 text-emerald-500/25 dark:border-emerald-400/30 dark:text-emerald-400/20',
                icon: CheckCircle2,
                iconColor: 'text-emerald-500',
                datePrefix: 'Redeemed on',
            };
        case 'expired':
            return {
                label: 'Expired',
                stampColor: 'border-red-500/40 text-red-500/25 dark:border-red-400/30 dark:text-red-400/20',
                icon: Clock,
                iconColor: 'text-red-500',
                datePrefix: 'Expired on',
            };
        case 'cancelled':
            return {
                label: 'Cancelled',
                stampColor: 'border-red-500/40 text-red-500/25 dark:border-red-400/30 dark:text-red-400/20',
                icon: XCircle,
                iconColor: 'text-red-500',
                datePrefix: 'Cancelled on',
            };
    }
});

const timeDisplay = computed(() => {
    if (!props.statusDate) return null;
    const date = new Date(props.statusDate);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMin = Math.floor(diffMs / 60_000);
    const diffHr = Math.floor(diffMs / 3_600_000);
    const diffDay = Math.floor(diffMs / 86_400_000);

    // Relative for recent, absolute for older
    if (diffMin < 1) return { prefix: statusConfig.value.datePrefix.replace(' on', ''), text: 'just now' };
    if (diffMin < 60) return { prefix: statusConfig.value.datePrefix.replace(' on', ''), text: `${diffMin} minute${diffMin !== 1 ? 's' : ''} ago` };
    if (diffHr < 24) return { prefix: statusConfig.value.datePrefix.replace(' on', ''), text: `${diffHr} hour${diffHr !== 1 ? 's' : ''} ago` };
    if (diffDay < 7) return { prefix: statusConfig.value.datePrefix.replace(' on', ''), text: `${diffDay} day${diffDay !== 1 ? 's' : ''} ago` };

    return {
        prefix: statusConfig.value.datePrefix,
        text: date.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }),
    };
});
</script>

<template>
    <Card class="overflow-hidden">
        <CardContent class="relative pt-4 pb-4">
            <div class="text-center space-y-1.5">
                <component
                    :is="statusConfig.icon"
                    :class="['h-6 w-6 mx-auto', statusConfig.iconColor]"
                />

                <p v-if="formattedAmount" class="text-2xl font-bold tracking-tight text-foreground">
                    {{ formattedAmount }}
                </p>

                <!-- Voucher code badge -->
                <div v-if="voucherCode" class="inline-flex items-center gap-1 px-3 py-0.5 text-xs font-mono font-semibold tracking-widest text-primary bg-primary/5 border border-primary/20 rounded-full">
                    <span class="text-primary/40" aria-hidden="true">||</span>
                    {{ voucherCode }}
                    <span class="text-primary/40" aria-hidden="true">||</span>
                </div>

                <!-- Date (relative for recent, absolute for older) -->
                <p v-if="timeDisplay" class="text-xs text-muted-foreground">
                    {{ timeDisplay.prefix }} {{ timeDisplay.text }}
                </p>
            </div>

            <!-- Tilted stamp overlay -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none select-none" aria-hidden="true">
                <div
                    :class="[
                        'border-[3px] rounded-md px-4 py-1 -rotate-12',
                        'text-[2rem] font-black uppercase tracking-[0.15em] leading-none',
                        statusConfig.stampColor,
                    ]"
                >
                    {{ statusConfig.label }}
                </div>
            </div>
        </CardContent>
    </Card>
</template>
