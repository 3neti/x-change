<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitCampaignActionStatus,
    CockpitCampaignPanelStatus,
    CockpitCampaignQuickGenerateLink,
    CockpitCampaignReadModel,
    CockpitCampaignSurface,
} from '../types';

const props = defineProps<{
    readModel?: CockpitCampaignReadModel;
}>();

const model = computed(() => props.readModel);
const facts = computed<Record<string, unknown>>(() => objectValue(model.value?.facts));
const campaignCard = computed<Record<string, unknown>>(() => objectValue(objectValue(facts.value.cards).campaign));

const isAvailable = computed(() => model.value?.authorized === true && model.value.status === 'available');

const campaignName = computed(() => stringValue(campaignCard.value.name) ?? 'Campaign read model unavailable');
const recipientCount = computed(() => {
    const value = campaignCard.value.recipient_count;

    if (typeof value === 'number' && Number.isFinite(value)) {
        return `${value} recipients`;
    }

    const normalized = stringValue(value);

    return normalized ? `${normalized} recipients` : 'Recipient count unavailable';
});
const planningKey = computed(() => stringValue(facts.value.planning_key) ?? 'No planning key');
const executionId = computed(() => stringValue(facts.value.execution_id) ?? 'No execution id');
const mutationReason = computed(() => stringValue(objectValue(model.value?.mutation).reason) ?? 'campaign-mutations-not-authorized');
const unavailableReason = computed(() => stringValue(objectValue(model.value?.redactions).reason) ?? 'read-model-not-available');
const quickGenerateLink = computed<CockpitCampaignQuickGenerateLink | null>(() => sanitizeQuickGenerateLink(model.value?.quick_generate_link));
const recipientQuickGenerateLinks = computed<CockpitCampaignQuickGenerateLink[]>(() => {
    if (!Array.isArray(model.value?.recipient_quick_generate_links)) {
        return [];
    }

    return model.value.recipient_quick_generate_links
        .map((link) => sanitizeQuickGenerateLink(link))
        .filter((link): link is CockpitCampaignQuickGenerateLink => link !== null);
});
const explorerHref = computed(() => {
    const normalizedPlanningKey = stringValue(facts.value.planning_key);
    const normalizedExecutionId = stringValue(facts.value.execution_id);

    if (!isAvailable.value || !normalizedPlanningKey || !normalizedExecutionId) {
        return null;
    }

    const query = new URLSearchParams({
        campaign_planning_key: normalizedPlanningKey,
        campaign_execution_id: normalizedExecutionId,
        campaign_source: 'campaign_cockpit',
    });

    return `/x/cockpit/pay-codes?${query.toString()}`;
});

const surfaces = computed<CockpitCampaignSurface[]>(() => {
    if (!Array.isArray(model.value?.surfaces)) {
        return [];
    }

    return model.value.surfaces
        .map((surface) => sanitizeSurface(surface))
        .filter((surface): surface is CockpitCampaignSurface => surface !== null);
});

const panels = computed<CockpitCampaignPanelStatus[]>(() => {
    const rawPanels = objectValue(facts.value.panels);

    return Object.entries(rawPanels)
        .map(([key, value]) => {
            const status = stringValue(objectValue(value).status);

            return status ? { key, status } : null;
        })
        .filter((panel): panel is CockpitCampaignPanelStatus => panel !== null);
});

const actions = computed<CockpitCampaignActionStatus[]>(() => {
    const rawActions = objectValue(facts.value.actions);

    return Object.entries(rawActions)
        .map(([key, value]) => {
            const enabled = objectValue(value).enabled === true;

            return {
                key,
                status: enabled ? 'available' : 'blocked',
            } satisfies CockpitCampaignActionStatus;
        });
});

function sanitizeSurface(surface: unknown): CockpitCampaignSurface | null {
    const payload = objectValue(surface);
    const key = stringValue(payload.key);
    const status = stringValue(payload.status);

    if (!key || !status) {
        return null;
    }

    return {
        key,
        status,
        enabled: payload.enabled === true,
        read_only: payload.read_only !== false,
        reason: stringValue(payload.reason),
    };
}

function sanitizeQuickGenerateLink(link: unknown): CockpitCampaignQuickGenerateLink | null {
    const payload = objectValue(link);
    const href = stringValue(payload.href);

    if (payload.enabled !== true || !href) {
        return null;
    }

    return {
        status: stringValue(payload.status) ?? 'available',
        enabled: true,
        label: stringValue(payload.label) ?? 'Open Quick Generate',
        href,
        route: stringValue(payload.route) ?? 'x-change.cockpit.quick-generate',
        read_only: payload.read_only !== false,
        mutates_campaign: payload.mutates_campaign === true,
        planning_key: stringValue(payload.planning_key),
        execution_id: stringValue(payload.execution_id),
        source: stringValue(payload.source) ?? 'campaign_cockpit',
        draft: objectValue(payload.draft),
    };
}

function objectValue(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};
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
        data-testid="cockpit-campaign-adoption-panel"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Campaign Cockpit Adoption
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    {{ campaignName }}
                </h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    {{ isAvailable ? recipientCount : 'Read-only boundary' }}
                </p>
            </div>

            <div class="rounded-lg border border-slate-100 px-3 py-2 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
                <p class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ isAvailable ? 'Read-only campaign context' : 'Campaign read model unavailable' }}
                </p>
                <p class="mt-1">
                    {{ isAvailable ? planningKey : unavailableReason }}
                </p>
                <p v-if="isAvailable" class="mt-1">
                    {{ executionId }}
                </p>
            </div>
        </div>

        <div v-if="surfaces.length > 0" class="mt-5 grid gap-3 md:grid-cols-2">
            <article
                v-for="surface in surfaces"
                :key="surface.key"
                class="rounded-lg border border-slate-100 px-3 py-3 dark:border-slate-800"
                data-testid="cockpit-campaign-surface"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ surface.key }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ surface.read_only ? 'read-only' : 'blocked' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ surface.status }}
                </p>
            </article>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Workspace panels
                </p>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p v-if="panels.length === 0">
                        No campaign panels authorized for display.
                    </p>
                    <p v-for="panel in panels" :key="panel.key">
                        {{ panel.key }}: {{ panel.status }}
                    </p>
                </div>
            </div>

            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Operator actions
                </p>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p v-if="actions.length === 0">
                        No campaign actions authorized for display.
                    </p>
                    <p v-for="action in actions" :key="action.key">
                        {{ action.key }}: {{ action.status }}
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">
                Mutation blocked
            </p>
            <p class="mt-1">
                {{ mutationReason }}
            </p>
        </div>

        <div
            v-if="recipientQuickGenerateLinks.length > 0"
            class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950"
            data-testid="cockpit-campaign-recipient-source-links"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">
                Recipient Quick Generate entry points
            </p>
            <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-100">
                Select a recipient to prefill the existing Quick Generate handoff. Campaign state is not mutated.
            </p>
            <div class="mt-3 grid gap-2 md:grid-cols-2">
                <a
                    v-for="(link, index) in recipientQuickGenerateLinks"
                    :key="link.recipient_id ?? link.href ?? index"
                    :href="link.href ?? '#'"
                    class="rounded-lg border border-emerald-200 bg-white px-3 py-3 text-sm text-emerald-950 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900 dark:text-emerald-50"
                    :data-testid="`cockpit-campaign-recipient-source-link-${index}`"
                >
                    <span class="block font-semibold">{{ link.label ?? 'Open Quick Generate' }}</span>
                    <span class="mt-1 block text-emerald-800 dark:text-emerald-100">
                        {{ link.draft?.recipient_reference ?? link.recipient_id ?? 'Recipient context' }}
                    </span>
                    <span class="mt-2 block text-xs text-emerald-700 dark:text-emerald-200">
                        {{ link.read_only ? 'read-only prefill' : 'campaign context' }} · {{ link.source }}
                    </span>
                </a>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <a
                v-if="quickGenerateLink"
                :href="quickGenerateLink.href ?? '#'"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-950 transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                data-testid="cockpit-campaign-quick-generate-link"
            >
                <span class="block font-semibold">{{ quickGenerateLink.label ?? 'Open Quick Generate' }}</span>
                <span class="mt-1 block text-emerald-800 dark:text-emerald-200">
                    Prefills the existing Quick Generate handoff
                </span>
                <span class="mt-2 block text-xs text-emerald-700 dark:text-emerald-200">
                    {{ quickGenerateLink.read_only ? 'read-only campaign context' : 'campaign context' }} · {{ quickGenerateLink.source }}
                </span>
            </a>
            <a
                v-if="explorerHref"
                :href="explorerHref"
                class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-3 text-sm text-sky-950 transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100"
                data-testid="cockpit-campaign-explorer-link"
            >
                <span class="block font-semibold">Open Pay Code Explorer</span>
                <span class="mt-1 block text-sky-800 dark:text-sky-200">
                    Existing read-only Cockpit route
                </span>
            </a>
            <span
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
                aria-disabled="true"
                data-testid="cockpit-campaign-workspace-link"
            >
                <span class="block font-semibold">Campaign workspace</span>
                <span class="mt-1 block">
                    Deferred until an explicit read-only workspace route is authorized.
                </span>
            </span>
        </div>
    </section>
</template>
