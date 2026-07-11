<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface CockpitBridge {
    status?: string | null;
    relationship?: string | null;
    cockpit_route?: string | null;
    legacy_owner?: string | null;
    mutation?: {
        legacy_page_remains_owner?: boolean | null;
        cockpit_replaces_legacy_page?: boolean | null;
        campaign_mutation_enabled?: boolean | null;
        funding_mutation_enabled?: boolean | null;
    } | null;
}

const props = defineProps<{
    bridge?: CockpitBridge | null;
    title?: string;
}>();

function bridgeLabel(value?: string | null): string {
    return String(value || 'cockpit bridge').replaceAll('-', ' ').replaceAll('_', ' ');
}
</script>

<template>
    <section
        v-if="props.bridge?.status === 'available'"
        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-100"
        data-testid="x-change-cockpit-bridge-callout"
    >
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                    Cockpit bridge
                </p>
                <h2 class="font-semibold">
                    {{ props.title ?? 'Available in Cockpit' }}
                </h2>
                <p class="max-w-3xl text-emerald-800 dark:text-emerald-200">
                    This legacy page remains the functional owner. Cockpit provides the operator shell for
                    {{ bridgeLabel(props.bridge.relationship) }} without replacing this workflow.
                </p>
                <p v-if="props.bridge.legacy_owner" class="text-xs text-emerald-700 dark:text-emerald-300">
                    Legacy owner: {{ props.bridge.legacy_owner }}
                </p>
            </div>

            <Link
                v-if="props.bridge.cockpit_route"
                :href="props.bridge.cockpit_route"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-400 dark:text-emerald-950 dark:hover:bg-emerald-300"
                data-testid="x-change-cockpit-bridge-link"
            >
                Open Cockpit
            </Link>
        </div>
    </section>
</template>
