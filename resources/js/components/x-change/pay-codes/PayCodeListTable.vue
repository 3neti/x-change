<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import PayCodeStatusBadge from './PayCodeStatusBadge.vue';
import { Eye, Copy, ExternalLink } from 'lucide-vue-next';

interface Voucher {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    currency?: string | null;
    status?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
    claim_url?: string | null;
}

interface Props {
    vouchers: Voucher[];
    showUrl?: (code: string) => string;
    claimUrl?: (code: string) => string;
}

const props = withDefaults(defineProps<Props>(), {
    showUrl: undefined,
    claimUrl: undefined,
});

const hasVouchers = computed(() => props.vouchers.length > 0);

function amountLabel(voucher: Voucher): string {
    if (voucher.formatted_amount) return voucher.formatted_amount;
    if (voucher.amount === null || voucher.amount === undefined || voucher.amount === '') return '—';

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: voucher.currency || 'PHP',
    }).format(Number(voucher.amount));
}

function dateLabel(value?: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
}

function goToShow(code: string): void {
    if (props.showUrl) {
        router.visit(props.showUrl(code));
    }
}

async function copyClaimUrl(code: string): Promise<void> {
    const url = props.claimUrl?.(code) ?? `/x/claim?code=${code}`;
    const absolute = url.startsWith('http') ? url : `${window.location.origin}${url}`;

    await navigator.clipboard.writeText(absolute);
}

function openClaim(code: string): void {
    const url = props.claimUrl?.(code) ?? `/x/claim?code=${code}`;
    window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <div v-if="hasVouchers" class="space-y-3">
        <Card
            v-for="voucher in vouchers"
            :key="voucher.code"
            class="overflow-hidden"
        >
            <CardContent class="p-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-lg font-semibold tracking-widest">
                                {{ voucher.code }}
                            </span>

                            <PayCodeStatusBadge
                                :status="voucher.status"
                                :redeemed_at="voucher.redeemed_at"
                                :expires_at="voucher.expires_at"
                            />
                        </div>

                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span>{{ amountLabel(voucher) }}</span>
                            <span>Created {{ dateLabel(voucher.created_at) }}</span>
                            <span v-if="voucher.redeemed_at">Redeemed {{ dateLabel(voucher.redeemed_at) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="copyClaimUrl(voucher.code)"
                        >
                            <Copy class="mr-1.5 h-3.5 w-3.5" />
                            Copy Link
                        </Button>

                        <Button
                            size="sm"
                            variant="outline"
                            @click="openClaim(voucher.code)"
                        >
                            <ExternalLink class="mr-1.5 h-3.5 w-3.5" />
                            Claim
                        </Button>

                        <Button
                            size="sm"
                            @click="goToShow(voucher.code)"
                        >
                            <Eye class="mr-1.5 h-3.5 w-3.5" />
                            View
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <div v-else class="rounded-lg border border-dashed p-8 text-center">
        <p class="font-medium">No Pay Codes found</p>
        <p class="mt-1 text-sm text-muted-foreground">
            Generate a Pay Code to get started.
        </p>
    </div>
</template>
