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
                </div>

                <div
                    data-testid="approval-voucher-code"
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 font-mono text-sm font-semibold tracking-widest text-primary"
                >
                    {{ voucher.code }}
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
