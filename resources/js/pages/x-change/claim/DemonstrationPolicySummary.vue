<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';

defineOptions({ layout: null });

defineProps<{
    summary: {
        reference: string;
        product: string;
        effective_at: string | null;
        expires_at: string | null;
        recorded_at: string;
        notice: string;
    };
    applicant?: Partial<Record<'name' | 'address' | 'birth_date' | 'mobile' | 'email', string | null>>;
}>();

const applicantFields = [
    { key: 'name', label: 'Full name' },
    { key: 'address', label: 'Address' },
    { key: 'birth_date', label: 'Date of birth' },
    { key: 'mobile', label: 'Mobile number' },
    { key: 'email', label: 'Email address' },
] as const;

function dateLabel(value: string | null): string {
    if (!value) return 'Not specified';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Not specified';
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Manila',
    }).format(date) + ' PHT';
}
</script>

<template>
    <ClaimStepShell tone="warning" width="md" :show-brand="false" :show-theme-picker="false">
        <Head title="Demonstration policy summary">
            <meta name="robots" content="noindex, nofollow" />
            <meta name="referrer" content="no-referrer" />
        </Head>
        <article class="flex min-w-0 flex-col gap-6 text-left" data-testid="demo-policy-summary">
            <header class="flex flex-col gap-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Demonstration only</p>
                <h1 class="text-2xl font-semibold">Demo policy summary</h1>
                <p class="text-sm text-muted-foreground">Your details were submitted and the demonstration response was recorded.</p>
            </header>
            <p class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm font-medium" data-testid="demo-policy-notice">
                {{ summary.notice }}
            </p>
            <dl class="flex min-w-0 flex-col gap-4 text-sm">
                <div><dt class="text-muted-foreground">Demo reference</dt><dd class="wrap-anywhere font-medium">{{ summary.reference }}</dd></div>
                <div><dt class="text-muted-foreground">Product</dt><dd class="wrap-anywhere font-medium">{{ summary.product }}</dd></div>
                <div><dt class="text-muted-foreground">Demonstration period starts</dt><dd>{{ dateLabel(summary.effective_at) }}</dd></div>
                <div><dt class="text-muted-foreground">Demonstration period ends</dt><dd>{{ dateLabel(summary.expires_at) }}</dd></div>
                <div><dt class="text-muted-foreground">Response recorded</dt><dd>{{ dateLabel(summary.recorded_at) }}</dd></div>
            </dl>
            <section class="flex min-w-0 flex-col gap-4" aria-labelledby="applicant-details-title" data-testid="demo-policy-applicant">
                <div>
                    <h2 id="applicant-details-title" class="text-lg font-semibold">Applicant details submitted</h2>
                    <p class="text-sm text-muted-foreground">These are the details submitted during the claim, not independently verified identity information.</p>
                </div>
                <dl class="grid min-w-0 grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div v-for="field in applicantFields" :key="field.key" class="min-w-0">
                        <dt class="text-muted-foreground">{{ field.label }}</dt>
                        <dd class="whitespace-pre-line wrap-anywhere font-medium" :data-testid="`demo-policy-applicant-${field.key}`">{{ applicant?.[field.key] || 'Not provided' }}</dd>
                    </div>
                </dl>
            </section>
            <p class="text-xs text-muted-foreground">This private page contains personal information and excludes payment-account details. The link expires and should not be shared. Anyone with this link can view the submitted details while it remains valid.</p>
        </article>
    </ClaimStepShell>
</template>
