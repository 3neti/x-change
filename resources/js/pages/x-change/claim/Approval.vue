<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Clock3 } from 'lucide-vue-next';
import type { CompiledClaimResultPayload } from '@/components/x-change/successCompiledClaimResult';
import { resolveApprovalPageViewModel } from '@/components/x-change/approvalPageViewModel';

defineOptions({ layout: null });

type VoucherProps = {
    code: string;
    amount?: number | string | null;
    currency?: string | null;
};

const props = defineProps<{
    voucher: VoucherProps;
    compiled_claim_result?: CompiledClaimResultPayload;
    message?: string | null;
}>();

const viewModel = computed(() =>
    resolveApprovalPageViewModel({
        compiledClaimResult: props.compiled_claim_result ?? null,
        message: props.message ?? null,
    })
);
</script>

<template>
    <Head title="Claim Awaiting Approval" />

    <main class="flex min-h-screen items-center justify-center bg-gradient-to-b from-primary/5 via-background to-background px-5 py-8 text-foreground">
        <Card class="w-full max-w-md border-0 bg-card/80 shadow-sm">
            <CardContent class="space-y-6 px-6 py-8 text-center">
                <Clock3 class="mx-auto h-16 w-16 text-amber-500" />

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ viewModel.status }}
                    </p>

                    <h1
                        data-testid="approval-title"
                        class="text-2xl font-semibold tracking-tight"
                    >
                        {{ viewModel.title }}
                    </h1>

                    <p
                        data-testid="approval-message"
                        class="text-sm text-muted-foreground"
                    >
                        {{ viewModel.message }}
                    </p>

                    <p
                        v-if="viewModel.headline"
                        data-testid="approval-headline"
                        class="text-sm font-medium text-foreground"
                    >
                        {{ viewModel.headline }}
                    </p>

                    <p
                        v-if="viewModel.metadataMessage"
                        data-testid="approval-metadata-message"
                        class="text-sm text-muted-foreground"
                    >
                        {{ viewModel.metadataMessage }}
                    </p>
                </div>

                <div
                    data-testid="approval-voucher-code"
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 font-mono text-sm font-semibold tracking-widest text-primary"
                >
                    {{ voucher.code }}
                </div>

                <div
                    v-if="viewModel.provider || viewModel.referenceId || viewModel.authorizationType || viewModel.expiresAt"
                    data-testid="approval-metadata"
                    class="space-y-1 rounded-lg border border-primary/10 bg-primary/5 p-4 text-left text-sm"
                >
                    <p v-if="viewModel.provider" data-testid="approval-provider">
                        Provider: {{ viewModel.provider }}
                    </p>

                    <p v-if="viewModel.authorizationType" data-testid="approval-authorization-type">
                        Authorization: {{ viewModel.authorizationType }}
                    </p>

                    <p v-if="viewModel.referenceId" data-testid="approval-reference-id">
                        Reference: {{ viewModel.referenceId }}
                    </p>

                    <p v-if="viewModel.expiresAt" data-testid="approval-expires-at">
                        Expires: {{ viewModel.expiresAt }}
                    </p>
                </div>

                <form
                    v-if="viewModel.showOtpForm"
                    data-testid="approval-otp-form"
                    class="space-y-3 rounded-lg border border-primary/10 bg-background p-4 text-left"
                >
                    <label
                        for="approval-otp"
                        class="text-sm font-medium"
                    >
                        One-time password
                    </label>

                    <input
                        id="approval-otp"
                        data-testid="approval-otp-input"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="Enter OTP"
                    />

                    <button
                        type="submit"
                        data-testid="approval-otp-submit"
                        class="w-full rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                        Verify OTP
                    </button>
                </form>

                <div
                    v-if="viewModel.showPollingNotice"
                    data-testid="approval-polling-notice"
                    class="rounded-lg border border-primary/10 bg-primary/5 p-4 text-sm text-muted-foreground"
                >
                    Waiting for provider confirmation. This page may update once confirmation is received.
                </div>

                <div
                    v-if="viewModel.showManualReviewNotice"
                    data-testid="approval-manual-review-notice"
                    class="rounded-lg border border-primary/10 bg-primary/5 p-4 text-sm text-muted-foreground"
                >
                    Your claim is under manual review. Please wait for further instructions.
                </div>

                <p
                    v-if="viewModel.amountText"
                    data-testid="approval-amount"
                    class="text-lg font-semibold"
                >
                    {{ viewModel.amountText }}
                </p>

                <ul
                    v-if="viewModel.messages.length > 0"
                    data-testid="approval-messages"
                    class="list-disc space-y-1 pl-5 text-left text-sm text-muted-foreground"
                >
                    <li
                        v-for="item in viewModel.messages"
                        :key="item"
                    >
                        {{ item }}
                    </li>
                </ul>
            </CardContent>
        </Card>
    </main>
</template>
