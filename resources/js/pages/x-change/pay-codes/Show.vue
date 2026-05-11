<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    PayCodeClaimHistory,
    PayCodeInstructionSummary,
    PayCodeQrSharePanel,
    PayCodeStatusBadge,
} from '@/components/x-change/pay-codes';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import {
    ArrowLeft,
    ExternalLink,
    Copy,
    CheckCircle2,
    Clock,
    ReceiptText,
} from 'lucide-vue-next';

defineOptions({
    layout: XChangeLayout,
});

interface Claim {
    id?: number | string;
    status?: string | null;
    mobile?: string | null;
    account_number?: string | null;
    transaction_id?: string | null;
    created_at?: string | null;
    redeemed_at?: string | null;
    error?: string | null;
}

interface Voucher {
    code: string;
    amount?: number | string | null;
    formatted_amount?: string | null;
    currency?: string | null;
    status?: string | null;
    created_at?: string | null;
    starts_at?: string | null;
    expires_at?: string | null;
    redeemed_at?: string | null;
    claim_url?: string | null;
    qr_code?: string | null;
    instructions?: Record<string, any> | null;
    metadata?: Record<string, any> | null;
    meta?: Record<string, any> | null;
    claims?: Claim[] | null;
}

interface Props {
    voucher: Voucher;
}

const props = defineProps<Props>();

const routes = useXChangeRoutes();

const claimUrl = computed(() => {
    if (props.voucher.claim_url) {
        return props.voucher.claim_url;
    }

    if (routes.claim?.startWithCode) {
        return routes.claim.startWithCode(props.voucher.code);
    }

    return `/x/claim?code=${encodeURIComponent(props.voucher.code)}`;
});

const metadata = computed(() => {
    return props.voucher.metadata ?? props.voucher.meta ?? {};
});

const hasMetadata = computed(() => Object.keys(metadata.value ?? {}).length > 0);

const lifecycleRows = computed(() => {
    return [
        {
            label: 'Created',
            value: formatDateTime(props.voucher.created_at),
        },
        {
            label: 'Starts',
            value: formatDateTime(props.voucher.starts_at),
        },
        {
            label: 'Expires',
            value: formatDateTime(props.voucher.expires_at),
        },
        {
            label: 'Redeemed',
            value: formatDateTime(props.voucher.redeemed_at),
        },
    ].filter((row) => row.value !== '—');
});

function amountLabel(): string {
    if (props.voucher.formatted_amount) {
        return props.voucher.formatted_amount;
    }

    if (props.voucher.amount === null || props.voucher.amount === undefined || props.voucher.amount === '') {
        return '—';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.voucher.currency || 'PHP',
    }).format(Number(props.voucher.amount));
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function goBack(): void {
    router.visit(routes.payCodes.index());
}

function openClaim(): void {
    window.open(claimUrl.value, '_blank', 'noopener,noreferrer');
}

async function copyClaimUrl(): Promise<void> {
    const absolute = claimUrl.value.startsWith('http')
        ? claimUrl.value
        : `${window.location.origin}${claimUrl.value}`;

    await navigator.clipboard.writeText(absolute);
}

async function copyCode(): Promise<void> {
    await navigator.clipboard.writeText(props.voucher.code);
}
</script>

<template>
    <Head :title="`Pay Code ${voucher.code}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
                <Button variant="ghost" class="-ml-3" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Pay Codes
                </Button>

                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="font-mono text-3xl font-semibold tracking-widest">
                            {{ voucher.code }}
                        </h1>

                        <PayCodeStatusBadge
                            :status="voucher.status"
                            :redeemed_at="voucher.redeemed_at"
                            :expires_at="voucher.expires_at"
                        />
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Pay Code lifecycle and redemption details.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" @click="copyCode">
                    <Copy class="mr-2 h-4 w-4" />
                    Copy Code
                </Button>

                <Button @click="openClaim">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    Open Claim
                </Button>
            </div>
        </div>

        <!-- Detail grid -->
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <!-- Main column -->
            <div class="space-y-6">
                <!-- Status / hero card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Pay Code Summary</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-lg border p-4">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                    Amount
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ amountLabel() }}
                                </p>
                            </div>

                            <div class="rounded-lg border p-4">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                    Status
                                </p>
                                <div class="mt-2">
                                    <PayCodeStatusBadge
                                        :status="voucher.status"
                                        :redeemed_at="voucher.redeemed_at"
                                        :expires_at="voucher.expires_at"
                                    />
                                </div>
                            </div>

                            <div class="rounded-lg border p-4">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                    Currency
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ voucher.currency ?? 'PHP' }}
                                </p>
                            </div>
                        </div>

                        <Separator />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="row in lifecycleRows"
                                :key="row.label"
                                class="flex items-center gap-3 rounded-lg bg-muted/40 p-3"
                            >
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.label }}
                                    </p>
                                    <p class="text-sm font-medium">
                                        {{ row.value }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Instructions -->
                <PayCodeInstructionSummary :instructions="voucher.instructions" />

                <!-- Claim history -->
                <PayCodeClaimHistory :claims="voucher.claims" />
            </div>

            <!-- Side column -->
            <div class="space-y-6">
                <PayCodeQrSharePanel
                    :code="voucher.code"
                    :claim_url="claimUrl"
                    :qr_code="voucher.qr_code"
                />

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ReceiptText class="h-4 w-4 text-muted-foreground" />
                            Metadata
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <pre
                            v-if="hasMetadata"
                            class="max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs"
                        >{{ JSON.stringify(metadata, null, 2) }}</pre>

                        <div v-else class="rounded-lg border border-dashed p-6 text-center">
                            <p class="text-sm font-medium">No metadata</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                System metadata will appear here if available.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
