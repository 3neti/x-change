<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { Loader2, AlertCircle, CheckCircle2, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { usePayCodeForm } from '@/composables/usePayCodeForm';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/x/dashboard' },
            { title: 'Pay Codes', href: '/x/pay-codes' },
            { title: 'Create', href: '/x/pay-codes/create' },
        ],
    },
});

const page = usePage();
const authUser = page.props.auth?.user as { id: number } | undefined;

const {
    form,
    estimate,
    estimateLoading,
    submitting,
    submitError,
    isAmountValid,
    canSubmit,
    totalFaceValue,
    totalEstimatedCost,
    submit,
    toggleInputField,
    reset,
} = usePayCodeForm({ issuerId: authUser?.id });

/**
 * Available redeemer input fields.
 * Defined from x-change pricelist config (instruction_items table).
 * Price is in centavos (minor units).
 */
const inputFieldItems = [
    { key: 'kyc', name: 'KYC Verification', price: 1800 },
    { key: 'otp', name: 'OTP Verification', price: 200 },
    { key: 'selfie', name: 'Selfie Photo', price: 300 },
    { key: 'signature', name: 'Digital Signature', price: 150 },
    { key: 'location', name: 'GPS Location', price: 100 },
    { key: 'email', name: 'Email Address', price: 50 },
    { key: 'mobile', name: 'Mobile Number', price: 50 },
    { key: 'name', name: 'Full Name', price: 30 },
    { key: 'address', name: 'Full Address', price: 50 },
    { key: 'birth_date', name: 'Birth Date', price: 30 },
    { key: 'gross_monthly_income', name: 'Monthly Income', price: 30 },
    { key: 'reference_code', name: 'Reference Code', price: 30 },
];

// Collapsible sections
const showValidation = ref(false);
const showFeedback = ref(false);
const showRider = ref(false);
const showAdvanced = ref(false);

// Success state
const successCode = ref<string | null>(null);

const formatCurrency = (amount: number, currency: string = 'PHP') => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(amount);
};

const handleSubmit = async () => {
    const result = await submit();
    if (result.success && result.code) {
        successCode.value = result.code;
        // Redirect after a brief delay to show success
        setTimeout(() => {
            router.visit(`/x/pay-codes/${result.code}`);
        }, 1500);
    }
};

const handleReset = () => {
    reset();
    successCode.value = null;
};
</script>

<template>
    <Head title="Create Pay Code" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="mx-auto grid w-full max-w-4xl gap-6 lg:grid-cols-[1fr_360px]">
            <!-- Main Form -->
            <div class="space-y-6">
                <!-- Success Alert -->
                <Alert v-if="successCode" variant="default" class="border-green-500 bg-green-50 dark:bg-green-950/20">
                    <CheckCircle2 class="h-4 w-4 text-green-600" />
                    <AlertDescription class="text-green-800 dark:text-green-200">
                        Pay Code <code class="rounded bg-green-100 px-2 py-0.5 font-mono text-sm dark:bg-green-900">{{ successCode }}</code> generated successfully! Redirecting...
                    </AlertDescription>
                </Alert>

                <!-- Error Alert -->
                <Alert v-if="submitError" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>{{ submitError }}</AlertDescription>
                </Alert>

                <!-- Amount & Count -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pay Code Details</CardTitle>
                        <CardDescription>
                            Set the disbursement amount and quantity.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="amount">Amount (₱)</Label>
                                <Input
                                    id="amount"
                                    v-model.number="form.amount"
                                    type="number"
                                    placeholder="0.00"
                                    min="0.01"
                                    step="0.01"
                                    :disabled="submitting"
                                />
                                <p v-if="form.amount !== null && !isAmountValid" class="text-xs text-destructive">
                                    Amount must be greater than zero.
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="count">Quantity</Label>
                                <Input
                                    id="count"
                                    v-model.number="form.count"
                                    type="number"
                                    placeholder="1"
                                    min="1"
                                    step="1"
                                    :disabled="submitting"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Input Fields -->
                <Card>
                    <CardHeader>
                        <CardTitle>Redeemer Input Fields</CardTitle>
                        <CardDescription>
                            Select what information the redeemer must provide. Each field adds a processing fee.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="item in inputFieldItems"
                                :key="item.key"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                :class="{
                                    'border-primary bg-primary/5': form.inputFields.includes(item.key),
                                }"
                            >
                                <Checkbox
                                    :model-value="form.inputFields.includes(item.key)"
                                    :disabled="submitting"
                                    @update:model-value="toggleInputField(item.key)"
                                />
                                <div class="flex-1 space-y-0.5">
                                    <p class="text-sm font-medium leading-none">{{ item.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatCurrency(item.price / 100) }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Validation Rules (Collapsible) -->
                <Card>
                    <CardHeader
                        class="cursor-pointer select-none"
                        @click="showValidation = !showValidation"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Validation Rules</CardTitle>
                                <CardDescription>
                                    Optional security restrictions for redemption.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="icon" class="shrink-0">
                                <ChevronUp v-if="showValidation" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="showValidation" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="secret">Secret Code</Label>
                            <Input
                                id="secret"
                                v-model="form.validationSecret"
                                placeholder="Optional secret for redemption"
                                :disabled="submitting"
                            />
                            <p class="text-xs text-muted-foreground">
                                Redeemer must enter this code to claim.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="validationMobile">Restrict to Mobile</Label>
                            <Input
                                id="validationMobile"
                                v-model="form.validationMobile"
                                placeholder="+639xxxxxxxxx"
                                :disabled="submitting"
                            />
                            <p class="text-xs text-muted-foreground">
                                Only this mobile number can redeem.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Feedback Channels (Collapsible) -->
                <Card>
                    <CardHeader
                        class="cursor-pointer select-none"
                        @click="showFeedback = !showFeedback"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Feedback Notifications</CardTitle>
                                <CardDescription>
                                    Get notified when the pay code is redeemed.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="icon" class="shrink-0">
                                <ChevronUp v-if="showFeedback" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="showFeedback" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="feedbackEmail">Email</Label>
                            <Input
                                id="feedbackEmail"
                                v-model="form.feedbackEmail"
                                type="email"
                                placeholder="notify@example.com"
                                :disabled="submitting"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="feedbackMobile">SMS</Label>
                            <Input
                                id="feedbackMobile"
                                v-model="form.feedbackMobile"
                                placeholder="+639xxxxxxxxx"
                                :disabled="submitting"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="feedbackWebhook">Webhook URL</Label>
                            <Input
                                id="feedbackWebhook"
                                v-model="form.feedbackWebhook"
                                type="url"
                                placeholder="https://your-endpoint.com/webhook"
                                :disabled="submitting"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Rider Options (Collapsible) -->
                <Card>
                    <CardHeader
                        class="cursor-pointer select-none"
                        @click="showRider = !showRider"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Rider (Post-Redemption)</CardTitle>
                                <CardDescription>
                                    Message or redirect shown after successful redemption.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="icon" class="shrink-0">
                                <ChevronUp v-if="showRider" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="showRider" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="riderMessage">Message</Label>
                            <Input
                                id="riderMessage"
                                v-model="form.riderMessage"
                                placeholder="Thank you for claiming!"
                                :disabled="submitting"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="riderUrl">Redirect URL</Label>
                            <Input
                                id="riderUrl"
                                v-model="form.riderUrl"
                                type="url"
                                placeholder="https://your-site.com/landing"
                                :disabled="submitting"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Advanced Options (Collapsible) -->
                <Card>
                    <CardHeader
                        class="cursor-pointer select-none"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Advanced Options</CardTitle>
                                <CardDescription>
                                    Code format, prefix, and expiration.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="icon" class="shrink-0">
                                <ChevronUp v-if="showAdvanced" class="h-4 w-4" />
                                <ChevronDown v-else class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="showAdvanced" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="prefix">Code Prefix</Label>
                                <Input
                                    id="prefix"
                                    v-model="form.prefix"
                                    placeholder="e.g. XC"
                                    :disabled="submitting"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="mask">Code Mask</Label>
                                <Input
                                    id="mask"
                                    v-model="form.mask"
                                    placeholder="e.g. ****-****-****"
                                    :disabled="submitting"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="ttl">Expiration (TTL)</Label>
                            <Input
                                id="ttl"
                                v-model="form.ttl"
                                placeholder="e.g. 30 days, 24 hours"
                                :disabled="submitting"
                            />
                            <p class="text-xs text-muted-foreground">
                                How long the pay code remains active.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar: Cost Breakdown & Submit -->
            <div class="space-y-6 lg:sticky lg:top-4 lg:self-start">
                <!-- Cost Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle>Cost Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <!-- Face Value -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                Face Value
                                <span v-if="form.count > 1" class="text-xs">(× {{ form.count }})</span>
                            </span>
                            <span class="font-medium">
                                {{ isAmountValid ? formatCurrency(totalFaceValue) : '—' }}
                            </span>
                        </div>

                        <!-- Estimate Charges -->
                        <template v-if="estimate">
                            <Separator />
                            <div class="space-y-2">
                                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Fees & Charges
                                </p>
                                <div
                                    v-for="charge in estimate.charges"
                                    :key="charge.index"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground">
                                        {{ charge.label }}
                                        <span v-if="charge.quantity > 1" class="text-xs">(× {{ charge.quantity }})</span>
                                    </span>
                                    <span>{{ formatCurrency(charge.price) }}</span>
                                </div>
                                <div v-if="estimate.charges.length === 0" class="text-xs text-muted-foreground">
                                    No additional charges.
                                </div>
                            </div>
                        </template>

                        <!-- Estimate Loading -->
                        <div v-else-if="estimateLoading && isAmountValid" class="space-y-2">
                            <Separator />
                            <Skeleton class="h-4 w-3/4" />
                            <Skeleton class="h-4 w-1/2" />
                        </div>

                        <Separator />

                        <!-- Total -->
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Total Deduction</span>
                            <span class="text-lg font-bold">
                                {{ isAmountValid ? formatCurrency(totalEstimatedCost) : '—' }}
                            </span>
                        </div>

                        <!-- Input fields summary -->
                        <div v-if="form.inputFields.length > 0" class="pt-2">
                            <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Required from Redeemer
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="field in form.inputFields"
                                    :key="field"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ field }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="flex-col gap-3">
                        <Button
                            class="w-full"
                            size="lg"
                            :disabled="!canSubmit || !!successCode"
                            @click="handleSubmit"
                        >
                            <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ submitting ? 'Generating...' : 'Generate Pay Code' }}
                        </Button>
                        <Button
                            variant="ghost"
                            class="w-full"
                            size="sm"
                            :disabled="submitting"
                            @click="handleReset"
                        >
                            Reset Form
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </div>
</template>
