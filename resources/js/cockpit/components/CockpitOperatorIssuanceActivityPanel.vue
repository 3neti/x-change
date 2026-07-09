<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitOperatorIssuanceActivityPresentation,
    CockpitOperatorIssuanceActivityReadModel,
} from '../types';

const props = defineProps<{
    readModel?: CockpitOperatorIssuanceActivityReadModel;
}>();

type SafePresentation = {
    id: string;
    title: string;
    subtitle: string;
    status: string;
    href?: string;
    correlationId?: string;
    handoffs: {
        journal: string;
        action: string;
        feedback: string;
    };
};

const presentations = computed<SafePresentation[]>(() => {
    if (!props.readModel?.authorized || !Array.isArray(props.readModel.presentations)) {
        return [];
    }

    return props.readModel.presentations
        .map((presentation) => sanitizePresentation(presentation))
        .filter((presentation): presentation is SafePresentation => presentation !== null);
});

const emptyTitle = computed(() => stringValue(props.readModel?.empty_state?.title) ?? 'No operator issuance activity available');
const emptyDescription = computed(() => (
    stringValue(props.readModel?.empty_state?.description)
    ?? 'Activity recording is not wired yet. Quick Generate can still use the existing issuance path.'
));

function sanitizePresentation(presentation: CockpitOperatorIssuanceActivityPresentation): SafePresentation | null {
    const id = stringValue(presentation.id);
    const title = stringValue(presentation.title);
    const subtitle = stringValue(presentation.subtitle);
    const status = stringValue(presentation.status);

    if (!id || !title || !subtitle || !status) {
        return null;
    }

    return {
        id,
        title,
        subtitle,
        status,
        href: safeDetailHref(presentation.detail_href),
        correlationId: stringValue(presentation.correlation_id),
        handoffs: {
            journal: stringValue(presentation.handoffs?.journal) ?? 'not_wired',
            action: stringValue(presentation.handoffs?.action) ?? 'not_wired',
            feedback: stringValue(presentation.handoffs?.feedback) ?? 'not_wired',
        },
    };
}

function safeDetailHref(value: unknown): string | undefined {
    const href = stringValue(value);

    if (!href?.startsWith('/x/cockpit/pay-codes/')) {
        return undefined;
    }

    return href;
}

function stringValue(value: unknown): string | undefined {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return undefined;
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-operator-issuance-activity-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Operator Issuance Activity
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate evidence
                </h3>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                presentation-only
            </span>
        </div>

        <div
            v-if="presentations.length === 0"
            class="mt-5 rounded-lg border border-dashed border-slate-200 p-4 dark:border-slate-800"
            data-testid="cockpit-operator-issuance-activity-empty"
        >
            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                {{ emptyTitle }}
            </p>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {{ emptyDescription }}
            </p>
        </div>

        <div v-else class="mt-5 space-y-3">
            <article
                v-for="presentation in presentations"
                :key="presentation.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-operator-issuance-activity-card"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ presentation.title }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ presentation.subtitle }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ presentation.status }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-2 text-xs text-slate-500 dark:text-slate-400 sm:grid-cols-3">
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Journal
                        </dt>
                        <dd>journal: {{ presentation.handoffs.journal }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Action
                        </dt>
                        <dd>action: {{ presentation.handoffs.action }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Feedback
                        </dt>
                        <dd>feedback: {{ presentation.handoffs.feedback }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-col gap-2 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <span v-if="presentation.correlationId">
                        Correlation: {{ presentation.correlationId }}
                    </span>
                    <a
                        v-if="presentation.href"
                        :href="presentation.href"
                        class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950 dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                        data-testid="cockpit-operator-issuance-activity-link"
                    >
                        Open Pay Code
                    </a>
                </div>
            </article>
        </div>
    </section>
</template>
