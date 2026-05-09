<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Copy, CheckCircle2, ArrowLeft, Clock, Calendar, DollarSign, Shield, Bell, MessageSquare, Settings } from 'lucide-vue-next';
import { useXChangeRoutes } from '@/composables/useXChangeRoutes';
import XChangeLayout from '@/layouts/x-change/XChangeLayout.vue';

const routes = useXChangeRoutes();

defineOptions({
    layout: [XChangeLayout, {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Pay Codes', href: '/x/pay-codes' },
            { title: 'Detail', href: '#' },
        ],
    }],
});

interface VoucherClaim {
    claim_number: number;
    claim_type: string;
    status: string;
    disbursed_amount_minor: number | null;
    currency: string;
    bank_code: string | null;
    account_number_masked: string | null;
    attempted_at: string | null;
    completed_at: string | null;
    failure_message: string | null;
}

interface VoucherInstructions {
    cash?: {
        amount: number;
        currency: string;
        validation?: {
            secret?: string | null;
            mobile?: string | null;
            payable?: string | null;
        };
        settlement_rail?: string | null;
    };
    inputs?: {
        fields: string[];
    };
    feedback?: {
        email?: string | null;
        mobile?: string | null;
        webhook?: string | null;
    };
    rider?: {
        message?: string | null;
        url?: string | null;
    };
    count?: number;
    prefix?: string;
    mask?: string;
    ttl?: string | null;
}

interface VoucherDetail {
    id: number;
    code: string;
    amount: number;
    currency: string;
    status: string;
    claimed: boolean;
    fully_claimed: boolean;
    issuer_id?: number;
    created_at?: string;
    expires_at?: string;
    starts_at?: string;
    redeemed_at?: string;
    instructions?: VoucherInstructions;
    claims?: VoucherClaim[];
}

const props = defineProps<{
    voucher: VoucherDetail;
}>();

const copied = ref(false);

const formatAmount = (amount: number, currency: string = 'PHP') => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string | null | undefined) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active': return 'default' as const;
        case 'redeemed': return 'secondary' as const;
        case 'cancelled': case 'expired': return 'destructive' as const;
        default: return 'outline' as const;
    }
};

const statusDescription = computed(() => {
    switch (props.voucher.status) {
        case 'active': return 'This pay code is active and can be redeemed';
        case 'redeemed': return 'This pay code has been successfully redeemed';
        case 'expired': return 'This pay code has expired';
        case 'cancelled': return 'This pay code has been cancelled';
        default: return '';
    }
});

const inst = computed(() => props.voucher.instructions);
const inputFields = computed(() => inst.value?.inputs?.fields ?? []);
const hasValidation = computed(() => {
    const v = inst.value?.cash?.validation;
    return v && (v.secret || v.mobile || v.payable);
});
const hasFeedback = computed(() => {
    const f = inst.value?.feedback;
    return f && (f.email || f.mobile || f.webhook);
});
const hasRider = computed(() => {
    const r = inst.value?.rider;
    return r && (r.message || r.url);
});
const hasCodeConfig = computed(() => {
    return inst.value && (inst.value.prefix || inst.value.mask || inst.value.ttl);
});
const hasAnyInstructions = computed(() => {
    return inputFields.value.length > 0 || hasValidation.value || hasFeedback.value || hasRider.value || hasCodeConfig.value;
});

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.voucher.code);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch { /* silent */ }
};
</script>

<template>
    <Head :title="`Pay Code ${voucher.code}`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <!-- Back + Header -->
            <div class="flex items-center justify-between">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="routes.payCodes.index">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Pay Codes
                    </Link>
                </Button>
            </div>

            <!-- Status Card -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-3">
                                <code class="rounded-md bg-muted px-3 py-1.5 font-mono text-lg font-bold">
                                    {{ voucher.code }}
                                </code>
                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="copyCode">
                                    <CheckCircle2 v-if="copied" class="h-4 w-4 text-green-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <Badge :variant="getStatusVariant(voucher.status)">
                                    {{ voucher.status }}
                                </Badge>
                                <span class="text-sm text-muted-foreground">{{ statusDescription }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-muted-foreground">Amount</div>
                            <div class="text-3xl font-bold">
                                {{ formatAmount(voucher.amount, voucher.currency) }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Details Grid -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Details
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="flex items-center gap-1 text-sm font-medium text-muted-foreground">
                                <DollarSign class="h-3.5 w-3.5" /> Amount
                            </dt>
                            <dd class="mt-1 text-sm font-semibold">{{ formatAmount(voucher.amount, voucher.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Currency</dt>
                            <dd class="mt-1 text-sm">{{ voucher.currency }}</dd>
                        </div>
                        <div v-if="inst?.cash?.settlement_rail">
                            <dt class="text-sm font-medium text-muted-foreground">Settlement Rail</dt>
                            <dd class="mt-1 text-sm">{{ inst.cash.settlement_rail }}</dd>
                        </div>
                        <div>
                            <dt class="flex items-center gap-1 text-sm font-medium text-muted-foreground">
                                <Clock class="h-3.5 w-3.5" /> Created
                            </dt>
                            <dd class="mt-1 text-sm">{{ formatDate(voucher.created_at) ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Expires</dt>
                            <dd class="mt-1 text-sm">{{ formatDate(voucher.expires_at) ?? 'Never' }}</dd>
                        </div>
                        <div v-if="voucher.starts_at">
                            <dt class="text-sm font-medium text-muted-foreground">Valid From</dt>
                            <dd class="mt-1 text-sm">{{ formatDate(voucher.starts_at) }}</dd>
                        </div>
                        <div v-if="voucher.redeemed_at">
                            <dt class="text-sm font-medium text-muted-foreground">Redeemed At</dt>
                            <dd class="mt-1 text-sm">{{ formatDate(voucher.redeemed_at) }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Instructions -->
            <Card v-if="hasAnyInstructions">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-4 w-4" />
                        Voucher Instructions
                    </CardTitle>
                    <CardDescription>Configuration set at issuance time</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <!-- Input Fields -->
                    <div v-if="inputFields.length > 0">
                        <h4 class="mb-2 text-sm font-medium text-muted-foreground">Required Input Fields</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="field in inputFields" :key="field" variant="secondary">
                                {{ field }}
                            </Badge>
                        </div>
                    </div>

                    <Separator v-if="inputFields.length > 0 && (hasValidation || hasFeedback || hasRider)" />

                    <!-- Validation -->
                    <div v-if="hasValidation">
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                            <Shield class="h-3.5 w-3.5" /> Validation Rules
                        </h4>
                        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div v-if="inst?.cash?.validation?.secret">
                                <dt class="text-xs text-muted-foreground">Secret Code</dt>
                                <dd class="text-sm font-mono">{{ inst.cash.validation.secret }}</dd>
                            </div>
                            <div v-if="inst?.cash?.validation?.mobile">
                                <dt class="text-xs text-muted-foreground">Mobile Restriction</dt>
                                <dd class="text-sm">{{ inst.cash.validation.mobile }}</dd>
                            </div>
                            <div v-if="inst?.cash?.validation?.payable">
                                <dt class="text-xs text-muted-foreground">Vendor Alias</dt>
                                <dd class="text-sm">{{ inst.cash.validation.payable }}</dd>
                            </div>
                        </dl>
                    </div>

                    <Separator v-if="hasValidation && (hasFeedback || hasRider)" />

                    <!-- Feedback -->
                    <div v-if="hasFeedback">
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                            <Bell class="h-3.5 w-3.5" /> Feedback Notifications
                        </h4>
                        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div v-if="inst?.feedback?.email">
                                <dt class="text-xs text-muted-foreground">Email</dt>
                                <dd class="text-sm">{{ inst.feedback.email }}</dd>
                            </div>
                            <div v-if="inst?.feedback?.mobile">
                                <dt class="text-xs text-muted-foreground">SMS</dt>
                                <dd class="text-sm">{{ inst.feedback.mobile }}</dd>
                            </div>
                            <div v-if="inst?.feedback?.webhook">
                                <dt class="text-xs text-muted-foreground">Webhook</dt>
                                <dd class="truncate text-sm font-mono">{{ inst.feedback.webhook }}</dd>
                            </div>
                        </dl>
                    </div>

                    <Separator v-if="hasFeedback && hasRider" />

                    <!-- Rider -->
                    <div v-if="hasRider">
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                            <MessageSquare class="h-3.5 w-3.5" /> Post-Redemption Rider
                        </h4>
                        <dl class="grid grid-cols-1 gap-2">
                            <div v-if="inst?.rider?.message">
                                <dt class="text-xs text-muted-foreground">Message</dt>
                                <dd class="text-sm">{{ inst.rider.message }}</dd>
                            </div>
                            <div v-if="inst?.rider?.url">
                                <dt class="text-xs text-muted-foreground">Redirect URL</dt>
                                <dd class="truncate text-sm font-mono">{{ inst.rider.url }}</dd>
                            </div>
                        </dl>
                    </div>

                    <Separator v-if="(hasValidation || hasFeedback || hasRider) && hasCodeConfig" />

                    <!-- Code Config -->
                    <div v-if="hasCodeConfig">
                        <h4 class="mb-2 text-sm font-medium text-muted-foreground">Code Configuration</h4>
                        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div v-if="inst?.prefix">
                                <dt class="text-xs text-muted-foreground">Prefix</dt>
                                <dd class="text-sm font-mono">{{ inst.prefix }}</dd>
                            </div>
                            <div v-if="inst?.mask">
                                <dt class="text-xs text-muted-foreground">Mask</dt>
                                <dd class="text-sm font-mono">{{ inst.mask }}</dd>
                            </div>
                            <div v-if="inst?.ttl">
                                <dt class="text-xs text-muted-foreground">TTL</dt>
                                <dd class="text-sm">{{ inst.ttl }}</dd>
                            </div>
                        </dl>
                    </div>
                </CardContent>
            </Card>

            <!-- Claim History -->
            <Card>
                <CardHeader>
                    <CardTitle>Claim History</CardTitle>
                    <CardDescription>Redemption and disbursement records</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!voucher.claims || voucher.claims.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No claims yet.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="claim in voucher.claims"
                            :key="claim.claim_number"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">#{{ claim.claim_number }}</span>
                                    <Badge variant="outline" class="text-xs">{{ claim.claim_type }}</Badge>
                                    <Badge
                                        :variant="claim.status === 'redeemed' || claim.status === 'succeeded' ? 'default' : claim.status === 'failed' ? 'destructive' : 'secondary'"
                                        class="text-xs"
                                    >
                                        {{ claim.status }}
                                    </Badge>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                    <span v-if="claim.bank_code">{{ claim.bank_code }}</span>
                                    <span v-if="claim.account_number_masked">{{ claim.account_number_masked }}</span>
                                    <span v-if="claim.attempted_at">{{ formatDate(claim.attempted_at) }}</span>
                                </div>
                                <p v-if="claim.failure_message" class="text-xs text-destructive">
                                    {{ claim.failure_message }}
                                </p>
                            </div>
                            <div v-if="claim.disbursed_amount_minor" class="text-right">
                                <span class="text-sm font-semibold">
                                    {{ formatAmount(claim.disbursed_amount_minor / 100, claim.currency) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
