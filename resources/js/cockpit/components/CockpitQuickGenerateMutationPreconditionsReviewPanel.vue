<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitQuickGenerateMutationPreconditionsReview,
    CockpitQuickGenerateMutationPreconditionsReviewItem,
} from '../types';

const props = defineProps<{
    mutationPreconditionsReview?: CockpitQuickGenerateMutationPreconditionsReview;
}>();

type DisplayItem = {
    key: string;
    label: string;
    status: string;
    reason: string;
};

const items = computed<DisplayItem[]>(() => {
    if (!Array.isArray(props.mutationPreconditionsReview?.items)) {
        return [];
    }

    return props.mutationPreconditionsReview.items
        .map((item): DisplayItem | null => normalizeItem(item))
        .filter((item): item is DisplayItem => item !== null);
});

const status = computed<string>(() => {
    return displayValue(props.mutationPreconditionsReview?.status, 'not_wired');
});

const recommendation = computed<string>(() => {
    return displayValue(props.mutationPreconditionsReview?.recommendation, 'not-loaded');
});

const redaction = computed<string>(() => {
    return displayValue(props.mutationPreconditionsReview?.redactions?.payloads, 'mutation-preconditions-review-only');
});

function normalizeItem(item: CockpitQuickGenerateMutationPreconditionsReviewItem): DisplayItem | null {
    const key = displayValue(item.key, '');
    const label = displayValue(item.label, '');

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        status: displayValue(item.status, 'unknown'),
        reason: displayValue(item.reason, 'No mutation precondition diagnostic is available.'),
    };
}

function displayValue(value: unknown, fallback: string): string {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return fallback;
    }

    const normalized = String(value).trim();

    return normalized === '' ? fallback : normalized;
}
</script>

<template>
    <section
        class="rounded-xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm dark:border-cyan-900/70 dark:bg-cyan-950/30"
        data-testid="cockpit-quick-generate-mutation-preconditions-review-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700 dark:text-cyan-300">
                    Handoff Preconditions Diagnostics
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Existing issuance handoff is ready; external side effects remain separately gated
                </h3>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                    Recommendation: <span class="font-semibold">{{ recommendation }}</span>
                </p>
            </div>
            <span class="rounded-full border border-cyan-200 bg-white px-3 py-1 text-xs font-semibold text-cyan-700 dark:border-cyan-900 dark:bg-slate-950 dark:text-cyan-300">
                {{ status }}
            </span>
        </div>

        <ul class="mt-4 grid gap-3">
            <li
                v-for="item in items"
                :key="item.key"
                class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-950/50"
                data-testid="cockpit-quick-generate-mutation-preconditions-review-item"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.label }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                            {{ item.key }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="item.status === 'passed'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                    >
                        {{ item.status }}
                    </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ item.reason }}
                </p>
            </li>
        </ul>

        <p class="mt-4 text-xs text-slate-600 dark:text-slate-300">
            Redaction policy: <span class="font-semibold">{{ redaction }}</span>
        </p>
        <p class="mt-2 text-xs font-medium text-cyan-800 dark:text-cyan-200">
            Provider, journal, action, feedback, and campaign mutations are not implied by the Quick Generate handoff.
        </p>
    </section>
</template>
