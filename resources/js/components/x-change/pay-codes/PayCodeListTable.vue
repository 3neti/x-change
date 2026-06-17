<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import PayCodeStatusBadge from './PayCodeStatusBadge.vue';
import { Eye, Copy, ExternalLink, KeyRound } from 'lucide-vue-next';

interface Voucher {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    currency?: string | null;
    status?: string | null;
    display_status?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    expires_at?: string | null;
    claim_url?: string | null;
    approval?: {
        required: boolean;
        type: 'otp' | null;
        provider: string | null;
        reference_id: string | null;
        message: string | null;
        action_url: string | null;
    } | null;
}

interface Props {
    vouchers: Voucher[];
    showUrl?: (code: string) => string;
    claimUrl?: (code: string) => string;
    approvalUrl?: (code: string) => string;
}

const props = withDefaults(defineProps<Props>(), {
    showUrl: undefined,
    claimUrl: undefined,
    approvalUrl: undefined,
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

    const timestamp = new Date(value).getTime();

    if (Number.isNaN(timestamp)) {
        return String(value);
    }

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

function needsApproval(voucher: Voucher): boolean {
    return voucher.approval?.required === true;
}

function displayStatus(voucher: Voucher): string | null | undefined {
    if (voucher.display_status) {
        return voucher.display_status;
    }

    if (needsApproval(voucher)) {
        return 'awaiting_approval';
    }

    return voucher.status;
}

function approvalActionUrl(voucher: Voucher): string {
    return voucher.approval?.action_url
        ?? props.approvalUrl?.(voucher.code)
        ?? `/x/pay-codes/${voucher.code}/approval`;
}

function goToApproval(voucher: Voucher): void {
    router.visit(approvalActionUrl(voucher));
}

function createdAt(voucher: Voucher): string | null {
    return (
        voucher.created_at ??
        (voucher as any).createdAt ??
        (voucher as any).created ??
        (voucher as any).issued_at ??
        (voucher as any).generated_at ??
        (voucher as any).created_at_human ??
        (voucher as any).formatted_created_at ??
        (voucher as any).timestamps?.created_at ??
        (voucher as any).dates?.created_at ??
        (voucher as any).meta?.created_at ??
        null
    );
}

function redeemedAt(voucher: Voucher): string | null {
    return (
        voucher.redeemed_at ??
        (voucher as any).redeemedAt ??
        (voucher as any).redeemed ??
        (voucher as any).redeemed_at_human ??
        (voucher as any).formatted_redeemed_at ??
        (voucher as any).timestamps?.redeemed_at ??
        (voucher as any).dates?.redeemed_at ??
        (voucher as any).meta?.redeemed_at ??
        null
    );
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
                                :status="displayStatus(voucher)"
                                :redeemed_at="voucher.redeemed_at"
                                :expires_at="voucher.expires_at"
                            />
                        </div>

                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span>{{ amountLabel(voucher) }}</span>
                            <span v-if="createdAt(voucher)">Created {{ dateLabel(createdAt(voucher)) }}</span>
                            <span v-if="redeemedAt(voucher)">Redeemed {{ dateLabel(redeemedAt(voucher)) }}</span>
                        </div>

                        <p
                            v-if="needsApproval(voucher)"
                            data-testid="pay-code-approval-helper"
                            class="text-sm text-amber-700"
                        >
                            Issuer OTP approval required before payout can complete.
                        </p>
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
                            v-if="needsApproval(voucher)"
                            size="sm"
                            variant="outline"
                            data-testid="pay-code-approval-action"
                            @click="goToApproval(voucher)"
                        >
                            <KeyRound class="mr-1.5 h-3.5 w-3.5" />
                            Approve
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
