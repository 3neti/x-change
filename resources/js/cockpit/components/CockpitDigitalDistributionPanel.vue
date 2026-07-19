<script setup lang="ts">
import type {
    CockpitDistributionAction,
    CockpitDistributionChannel,
} from '../types';

const props = defineProps<{
    channels: CockpitDistributionChannel[];
    actions: CockpitDistributionAction[];
}>();

function enabledActionCount(): number {
    return props.actions.filter((action) => !action.disabled).length;
}

function disabledActionCount(): number {
    return props.actions.filter((action) => action.disabled).length;
}

function stringValue(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function deliveryMetadata(channel: CockpitDistributionChannel): Array<{ label: string; value: string }> {
    const metadata = channel.metadata ?? {};
    const providerStatus = stringValue(metadata.provider_status);
    const attemptCount = stringValue(metadata.attempt_count);
    const maxAttempts = stringValue(metadata.max_attempts);
    const communicationStateOnly = stringValue(metadata.communication_state_only);

    return [
        providerStatus === null ? null : { label: 'Provider Status', value: providerStatus },
        attemptCount === null ? null : { label: 'Attempts', value: `${attemptCount}${maxAttempts === null ? '' : `/${maxAttempts}`}` },
        communicationStateOnly === null ? null : { label: 'Communication State Only', value: communicationStateOnly },
    ].filter((item): item is { label: string; value: string } => item !== null);
}

function actionMetadata(action: CockpitDistributionAction): Array<{ label: string; value: string }> {
    const metadata = action.metadata ?? {};
    const targetRoute = stringValue(metadata.target_route);
    const targetType = stringValue(metadata.target_type);
    const presentationRun = stringValue(metadata.presentation_run);
    const executesAction = stringValue(metadata.executes_action);

    return [
        targetRoute === null ? null : { label: 'Target Route', value: targetRoute },
        targetType === null ? null : { label: 'Target Type', value: targetType },
        presentationRun === null ? null : { label: 'Presentation Run', value: presentationRun },
        executesAction === null ? null : { label: 'Executes Action', value: executesAction },
    ].filter((item): item is { label: string; value: string } => item !== null);
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-digital-distribution-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Digital Distribution
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Delivery channel status
        </h3>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-3"
            data-testid="cockpit-distribution-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Channels
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ channels.length }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Available Actions
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ enabledActionCount() }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Blocked Actions
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ disabledActionCount() }}
                </p>
            </div>
        </div>

        <div class="mt-5 grid gap-3">
            <article
                v-for="channel in channels"
                :key="channel.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-distribution-channel"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ channel.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ channel.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ channel.helper }}
                </p>
                <dl
                    v-if="deliveryMetadata(channel).length > 0"
                    class="mt-3 grid gap-2 rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-950/50 sm:grid-cols-3"
                    data-testid="cockpit-distribution-channel-metadata"
                >
                    <div
                        v-for="item in deliveryMetadata(channel)"
                        :key="`${channel.key}-${item.label}`"
                    >
                        <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ item.label }}
                        </dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>
            </article>
        </div>

        <div class="mt-5 grid gap-2 sm:grid-cols-2">
            <div
                v-for="action in actions"
                :key="action.key"
                class="rounded-md border border-slate-200 p-3 dark:border-slate-700"
            >
                <button
                    :disabled="action.disabled"
                    :title="action.reason"
                    type="button"
                    class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                    data-testid="cockpit-distribution-action"
                >
                    {{ action.label }}
                </button>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-distribution-action-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        Action status details
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ action.reason }}
                    </p>
                    <dl
                        v-if="actionMetadata(action).length > 0"
                        class="mt-3 grid gap-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-950/50 sm:grid-cols-2"
                        data-testid="cockpit-distribution-action-metadata"
                    >
                        <div
                            v-for="item in actionMetadata(action)"
                            :key="`${action.key}-${item.label}`"
                        >
                            <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1 break-words text-slate-700 dark:text-slate-200">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </div>
        </div>
    </section>
</template>
