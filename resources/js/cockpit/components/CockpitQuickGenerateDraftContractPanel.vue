<script setup lang="ts">
import { computed } from 'vue';
import type { CockpitQuickGenerateDraftContract } from '../types';

const props = defineProps<{
    draftContract?: CockpitQuickGenerateDraftContract;
}>();

type DraftField = {
    key: string;
    label: string;
    value: string;
};

const draftFields = computed<DraftField[]>(() => {
    const draft = props.draftContract ?? {};

    return [
        {
            key: 'template_key',
            label: 'template_key',
            value: displayValue(draft.template_key),
        },
        {
            key: 'amount',
            label: 'amount',
            value: displayValue(draft.amount),
        },
        {
            key: 'currency',
            label: 'currency',
            value: displayValue(draft.currency),
        },
        {
            key: 'recipient_reference',
            label: 'recipient_reference',
            value: displayValue(draft.recipient_reference),
        },
        {
            key: 'purpose',
            label: 'purpose',
            value: displayValue(draft.purpose),
        },
        {
            key: 'idempotency_key',
            label: 'idempotency_key',
            value: displayValue(draft.idempotency_key),
        },
    ];
});

const schema = computed<string>(() => {
    return displayValue(props.draftContract?.schema, 'x-change.cockpit.quick-generate-draft.v1');
});

const status = computed<string>(() => {
    return displayValue(props.draftContract?.status, 'draft_only');
});

const redaction = computed<string>(() => {
    return displayValue(props.draftContract?.redactions?.payloads, 'draft-shape-only');
});

function displayValue(value: unknown, fallback = 'Pending'): string {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return fallback;
    }

    const normalized = String(value).trim();

    return normalized === '' ? fallback : normalized;
}
</script>

<template>
    <section
        class="rounded-xl border border-sky-200 bg-sky-50 p-5 shadow-sm dark:border-sky-900/70 dark:bg-sky-950/30"
        data-testid="cockpit-quick-generate-draft-contract-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-300">
                    Request Draft Contract
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Local draft shape for a future issuance request
                </h3>
            </div>
            <span class="rounded-full border border-sky-200 bg-white px-3 py-1 text-xs font-semibold text-sky-700 dark:border-sky-900 dark:bg-slate-950 dark:text-sky-300">
                {{ status }}
            </span>
        </div>

        <dl class="mt-4 grid gap-3 text-sm">
            <div class="rounded-lg border border-sky-200 bg-white/70 p-3 dark:border-sky-900/60 dark:bg-slate-950/50">
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    schema
                </dt>
                <dd class="mt-1 break-words font-mono text-xs text-slate-900 dark:text-slate-100">
                    {{ schema }}
                </dd>
            </div>
            <div
                v-for="field in draftFields"
                :key="field.key"
                class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50"
                data-testid="cockpit-quick-generate-draft-field"
            >
                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                    {{ field.label }}
                </dt>
                <dd class="mt-1 text-sm font-medium text-slate-950 dark:text-slate-50">
                    {{ field.value }}
                </dd>
            </div>
        </dl>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-sky-800 dark:text-sky-200">
            Drafts are local and read-only in Slice 18.
        </p>
    </section>
</template>
