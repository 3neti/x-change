<script setup lang="ts">
import { computed, ref } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { AlertCircle, Clock } from 'lucide-vue-next';
import XRayAuthorsTicker from '@/components/x-change/XRayAuthorsTicker.vue';
import {
    resolveXRayRequirementViewModel,
    resolveXRayStatusViewModel,
} from '@/components/x-change/xrayClaimPreviewViewModel';
import type { XRayClaimPreviewResult } from '@/components/x-change/xrayClaimPreviewViewModel';

interface XRayServiceInfo {
    host_name?: string;
    host_version?: string;
    x_change_version?: string;
    form_flow_version?: string;
    x_ray_version?: string;
    publisher?: string;
    authors?: string[];
    contributors?: string[];
}

interface XRaySliceRow {
    id?: string | null;
    label?: string | null;
    amount_minor?: number | null;
    status?: string | null;
    status_label?: string | null;
    claimed_at?: string | null;
    max_slices?: number | null;
    min_amount_minor?: number | null;
    claims_used?: number | null;
    claims_remaining?: number | null;
    is_final_claim?: boolean | null;
}

interface XRayValueUse {
    mode: 'whole' | 'flexible' | 'equal' | 'scheduled' | 'stored_value';
    amount_basis: 'whole' | 'remaining' | 'available_now' | 'next_available' | 'starting' | 'unavailable';
    amount_minor?: number | null;
    minimum_minor?: number;
    claims_remaining?: number;
    portion_number?: number;
    portion_count?: number;
    available_at?: string;
    collection_terms?: { amount: string; otp_above_minor?: number };
}

const props = withDefaults(defineProps<{
    result?: XRayClaimPreviewResult | null;
    loading?: boolean;
    error?: string | null;
    hideSlicePlan?: boolean;
    compact?: boolean;
}>(), {
    hideSlicePlan: false,
    compact: false,
});
const activeTab = ref<'instructions' | 'system-info'>('instructions');
const expanded = ref(!props.compact);
function switchTab(tab: 'instructions' | 'system-info', event: KeyboardEvent): void {
    activeTab.value = tab;
    const target = event.currentTarget as HTMLElement;
    target.parentElement?.querySelector<HTMLElement>(`#xray-${tab}-tab`)?.focus();
}
const disclosure = (key: string): NonNullable<XRayClaimPreviewResult['disclosures']>[number] | undefined =>
    props.result?.disclosures?.find((item) => item.key === key);
const amount = computed(() => disclosure('amount')?.value);
const valueUse = computed<XRayValueUse | null>(() => {
    const value = disclosure('value_use')?.value;
    return value && typeof value === 'object' && !Array.isArray(value) && 'mode' in value
        ? value as XRayValueUse
        : null;
});
const valueUseLabel = computed(() => {
    const value = valueUse.value;
    if (!value) return 'Pay Code value';
    if (value.amount_basis === 'unavailable') return 'Balance unavailable';
    if (value.mode === 'whole') return 'Amount available in one claim';
    if (value.mode === 'flexible') return 'Remaining balance';
    if (value.mode === 'stored_value') return value.amount_basis === 'starting' ? 'Starting balance' : 'Remaining balance';
    return value.amount_basis === 'next_available' ? 'Next available amount' : 'Available now';
});
const valueUseAmount = computed(() => {
    const value = valueUse.value;
    if (!value) return amount.value;
    return typeof value.amount_minor === 'number' ? formatSliceAmount(value.amount_minor) : null;
});
const valueUseDetails = computed(() => {
    const value = valueUse.value;
    if (!value) return [];
    const details: string[] = [];
    if (value.mode === 'flexible') {
        if (typeof value.minimum_minor === 'number') details.push(`Minimum ${formatSliceAmount(value.minimum_minor)}`);
        if (typeof value.claims_remaining === 'number') details.push(`${value.claims_remaining} claims remaining`);
    }
    if (['equal', 'scheduled'].includes(value.mode) && value.portion_number) {
        details.push(`Portion ${value.portion_number}${value.portion_count ? ` of ${value.portion_count}` : ''}`);
    }
    if (value.available_at && value.amount_basis === 'next_available') {
        const date = new Date(value.available_at);
        if (!Number.isNaN(date.getTime())) details.push(new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeZone: 'Asia/Manila' }).format(date));
    }
    if (value.mode === 'stored_value') {
        details.push('Collector sets the amount');
        if (value.collection_terms?.otp_above_minor) details.push(`OTP may be required above ${formatSliceAmount(value.collection_terms.otp_above_minor)}`);
    }
    return details;
});
const expiresAt = computed(() => disclosure('expires_at')?.value);
const systemDisclosures = computed(() =>
    (props.result?.disclosures ?? []).filter((item) =>
        ['issuer', 'funding_source', 'provider', 'issued_at'].includes(item.key),
    ),
);
const serviceInfo = computed<XRayServiceInfo | null>(() => {
    const value = disclosure('service_info')?.value;
    return value && typeof value === 'object' && !Array.isArray(value)
        ? value as XRayServiceInfo
        : null;
});
const serviceRows = computed(() => {
    const info = serviceInfo.value;
    if (!info) return [];
    const rows: Array<[string, string | undefined]> = [
        ['Application', info.host_name],
        ['Application version', info.host_version],
        ['x-change', info.x_change_version],
        ['form-flow', info.form_flow_version],
        ['x-ray', info.x_ray_version],
        ['Publisher', info.publisher],
    ];
    return rows.filter((row): row is [string, string] => Boolean(row[1]));
});
const authors = computed(() => {
    const names = serviceInfo.value?.authors?.length
        ? serviceInfo.value.authors
        : serviceInfo.value?.contributors ?? [];

    return names.filter((name): name is string => typeof name === 'string' && name.trim() !== '');
});

// This is the redeemer-facing "Pay Code preview" panel. It is presentational
// only: the underlying x-ray disclosure policy already decided what this
// viewer is allowed to see (see DefaultXRayDisclosurePolicy). This component
// never adds, infers, or re-derives hidden data -- it only makes what was
// already disclosed easier to scan.
const status = computed(() =>
    resolveXRayStatusViewModel({
        status: props.result?.status,
        visible: props.result?.visible,
    }),
);

const requirements = computed(() =>
    (props.result?.requirements ?? []).map(resolveXRayRequirementViewModel),
);
const securityRequirements = computed(() =>
    requirements.value.filter((item) =>
        ['assigned_mobile', 'secret', 'passcode'].includes(item.key),
    ),
);
const inputRequirements = computed(() =>
    requirements.value.filter((item) =>
        !['assigned_mobile', 'secret', 'passcode'].includes(item.key),
    ),
);
const requirementSummary = computed(() => {
    if (requirements.value.length === 0) {
        return 'No additional information required';
    }

    const labels = requirements.value.slice(0, 3).map((item) => item.label);
    const remaining = requirements.value.length - labels.length;

    return `${labels.join(' · ')}${remaining > 0 ? ` · +${remaining} more` : ''}`;
});
const formattedExpiry = computed(() => {
    if (!expiresAt.value) return null;

    const date = new Date(String(expiresAt.value));
    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(date);
});

// Anything disclosed besides the status itself (e.g. amount/issuer for a
// non-guest audience). Guests typically see nothing here, since the status
// is already shown in the friendly header above.
const extraDisclosures = computed(() =>
    (props.result?.disclosures ?? []).filter(
        (item) => !['status', 'remaining_slices', 'amount', 'value_use', 'issuer', 'funding_source', 'provider', 'issued_at', 'expires_at', 'reference', 'service_info'].includes(item.key),
    ),
);
const sliceRows = computed<XRaySliceRow[]>(() => {
    const value = (props.result?.disclosures ?? []).find(
        (item) => item.key === 'remaining_slices',
    )?.value;

    return Array.isArray(value)
        ? value.filter(
              (item): item is XRaySliceRow =>
                  typeof item === 'object' && item !== null,
          )
        : [];
});
const flexiblePlan = computed<XRaySliceRow | null>(
    () =>
        sliceRows.value.find((row) => row.id === 'remaining_capacity') ?? null,
);
const sliceActivityRows = computed<XRaySliceRow[]>(() =>
    flexiblePlan.value
        ? sliceRows.value.filter((row) => row.id !== 'remaining_capacity')
        : sliceRows.value,
);

function formatSliceAmount(value: number | null | undefined): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(value ?? 0) / 100);
}

function formatSliceClaimedAt(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const instant = new Date(value);

    if (Number.isNaN(instant.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(instant);
}

const stages = computed(() => props.result?.stages ?? []);
const isPlainClaimable = computed(
    () => props.result?.visible !== false && props.result?.status === 'claimable',
);
const headingLabel = computed(() =>
    isPlainClaimable.value ? 'Pay Code verified' : 'Pay Code preview',
);
const showStatusBadge = computed(() => !isPlainClaimable.value);

function stageText(stage: NonNullable<XRayClaimPreviewResult['stages']>[number]): string {
    const payload = stage.payload ?? {};
    const raw = String(
        payload.message ??
            payload.body ??
            payload.content ??
            stage.message ??
            stage.body ??
            stage.title ??
            'Issuer-provided preview content is available.',
    );

    if (String(payload.content_type ?? '').toLowerCase() === 'html') {
        return raw
            .replace(/<style[\s\S]*?<\/style>/gi, ' ')
            .replace(/<script[\s\S]*?<\/script>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    return raw;
}
</script>

<template>
    <div class="space-y-2" data-testid="xray-claim-preview">
        <Card v-if="loading">
            <CardContent class="py-6 text-center text-sm text-muted-foreground">
                Checking Pay Code...
            </CardContent>
        </Card>

        <Alert v-else-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                {{ error }}
            </AlertDescription>
        </Alert>

        <template v-else-if="result">
            <Card>
                <CardContent class="space-y-4 py-4">
                    <!-- Status -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <component
                                :is="status.icon"
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p
                                class="truncate text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground"
                            >
                                {{ headingLabel }}
                            </p>
                        </div>
                        <Badge
                            v-if="showStatusBadge"
                            :variant="status.badgeVariant"
                            class="shrink-0"
                        >
                            {{ status.label }}
                        </Badge>
                    </div>
                    <p
                        v-if="status.description"
                        class="text-sm text-foreground"
                        data-testid="xray-status-description"
                    >
                        {{ status.description }}
                    </p>

                    <div v-if="valueUse || amount" class="rounded-xl border border-primary/15 bg-primary/5 p-4" data-testid="xray-claim-amount">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <span>{{ valueUseLabel }}</span>
                        </div>
                        <p v-if="valueUseAmount" class="mt-1 text-2xl font-bold tabular-nums text-foreground">{{ valueUseAmount }}</p>
                        <p v-if="valueUseDetails.length" class="mt-1 text-xs text-muted-foreground">{{ valueUseDetails.join(' · ') }}</p>
                    </div>

                    <button
                        v-if="compact"
                        type="button"
                        class="w-full rounded-xl border p-3 text-left transition hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-primary"
                        :aria-expanded="expanded"
                        aria-controls="xray-instructions-panel xray-system-info-panel"
                        data-testid="xray-requirements-toggle"
                        @click="expanded = !expanded"
                    >
                        <span class="flex items-center justify-between gap-3 text-sm font-semibold text-foreground">
                            <span>What you'll need</span>
                            <span class="text-xs text-muted-foreground">{{ expanded ? 'Hide details' : 'Review details' }}</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-muted-foreground" data-testid="xray-requirements-summary">{{ requirementSummary }}</span>
                    </button>

                    <div v-if="!compact || expanded" class="grid grid-cols-2 rounded-lg bg-muted/50 p-1" role="tablist" aria-label="Pay Code details">
                        <button type="button" role="tab" id="xray-instructions-tab" aria-controls="xray-instructions-panel" :aria-selected="activeTab === 'instructions'" :tabindex="activeTab === 'instructions' ? 0 : -1" class="min-h-10 rounded-md px-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary" :class="activeTab === 'instructions' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'" @click="activeTab = 'instructions'" @keydown.right.prevent="switchTab('system-info', $event)" @keydown.left.prevent="switchTab('system-info', $event)">Instructions</button>
                        <button type="button" role="tab" id="xray-system-info-tab" aria-controls="xray-system-info-panel" :aria-selected="activeTab === 'system-info'" :tabindex="activeTab === 'system-info' ? 0 : -1" class="min-h-10 rounded-md px-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary" :class="activeTab === 'system-info' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'" @click="activeTab = 'system-info'" @keydown.right.prevent="switchTab('instructions', $event)" @keydown.left.prevent="switchTab('instructions', $event)">System Info</button>
                    </div>

                    <div v-show="(!compact || expanded) && activeTab === 'instructions'" id="xray-instructions-panel" role="tabpanel" aria-labelledby="xray-instructions-tab" class="space-y-4">

                    <section v-if="formattedExpiry" class="rounded-xl border p-4" data-testid="xray-validity">
                        <div class="flex items-start gap-3">
                            <Clock class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-foreground">Expires on</h3>
                                <p class="text-sm text-muted-foreground">{{ formattedExpiry }}</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="inputRequirements.length" class="space-y-3 rounded-xl border p-4" data-testid="xray-required-inputs">
                        <div>
                            <h3 class="text-base font-semibold text-foreground">Required Inputs</h3>
                            <p class="text-sm text-muted-foreground">Please prepare to provide the following:</p>
                        </div>
                        <ul class="space-y-2">
                            <li v-for="item in inputRequirements" :key="item.key" class="flex items-start gap-3 rounded-lg border bg-muted/20 p-3">
                                <component :is="item.icon" v-if="item.icon" class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground">{{ item.label }}</p>
                                    <p v-if="item.description" class="text-xs leading-relaxed text-muted-foreground">{{ item.description }}</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section v-if="securityRequirements.length" class="space-y-3 rounded-xl border p-4" data-testid="xray-security-requirements">
                        <h3 class="text-base font-semibold text-foreground">Assignment &amp; Security</h3>
                        <ul class="space-y-3">
                            <li v-for="item in securityRequirements" :key="item.key" class="flex items-start gap-3">
                                <component :is="item.icon" v-if="item.icon" class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground">{{ item.key === 'assigned_mobile' ? 'Assigned to a mobile number' : item.label + ' required' }}</p>
                                    <p v-if="item.description" class="text-xs leading-relaxed text-muted-foreground">{{ item.description }}</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <p v-if="!requirements.length" class="rounded-xl border p-4 text-sm text-muted-foreground" data-testid="xray-no-requirements">
                        No additional information required to claim this Pay Code.
                    </p>

                    <div
                        v-if="sliceRows.length && !hideSlicePlan"
                        class="space-y-2"
                        data-testid="xray-slice-plan"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground"
                        >
                            Slices
                        </p>
                        <div
                            v-if="flexiblePlan"
                            class="grid gap-2 rounded-lg border bg-muted/20 p-3 sm:grid-cols-3"
                            data-testid="xray-flexible-plan-summary"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Available
                                </p>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{
                                        formatSliceAmount(
                                            flexiblePlan.amount_minor,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Claims remaining
                                </p>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ flexiblePlan.claims_remaining }} of
                                    {{ flexiblePlan.max_slices }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Minimum claim
                                </p>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{
                                        formatSliceAmount(
                                            flexiblePlan.min_amount_minor,
                                        )
                                    }}
                                </p>
                            </div>
                            <p
                                v-if="flexiblePlan.is_final_claim"
                                class="text-xs font-medium text-foreground sm:col-span-3"
                            >
                                Final claim — use the full remaining balance.
                            </p>
                        </div>
                        <ul v-if="sliceActivityRows.length" class="grid gap-2">
                            <li
                                v-for="slice in sliceActivityRows"
                                :key="String(slice.id)"
                                class="flex min-w-0 items-center justify-between gap-3 rounded-lg border bg-muted/20 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ slice.label || 'Slice' }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ slice.status_label || 'Available' }}
                                    </p>
                                    <p
                                        v-if="
                                            formatSliceClaimedAt(
                                                slice.claimed_at,
                                            )
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        Claimed
                                        {{
                                            formatSliceClaimedAt(
                                                slice.claimed_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-foreground">
                                    {{ formatSliceAmount(slice.amount_minor) }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Issuer preview -->
                    <div v-if="stages.length" class="space-y-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground"
                        >
                            Note from the issuer
                        </p>
                        <div
                            v-for="(stage, index) in stages"
                            :key="stage.key || index"
                            class="rounded-lg border bg-primary/5 p-3 text-sm text-foreground"
                        >
                            {{ stageText(stage) }}
                        </div>
                    </div>
                    </div>

                    <div v-show="(!compact || expanded) && activeTab === 'system-info'" id="xray-system-info-panel" role="tabpanel" aria-labelledby="xray-system-info-tab" class="space-y-4">
                        <dl v-if="systemDisclosures.length || extraDisclosures.length" class="divide-y rounded-xl border px-4">
                            <div v-for="item in [...systemDisclosures, ...extraDisclosures]" :key="item.key" class="flex items-start justify-between gap-4 py-3 text-sm">
                                <dt class="text-muted-foreground">{{ item.label || item.key }}</dt>
                                <dd class="min-w-0 break-words text-right font-medium text-foreground">{{ item.key === 'issued_at' ? new Date(String(item.value)).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : item.value }}</dd>
                            </div>
                        </dl>
                        <div v-if="serviceRows.length || authors.length" class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground">About this service</p>
                            <dl class="divide-y rounded-xl border px-4">
                                <div v-for="[label, value] in serviceRows" :key="label" class="flex items-start justify-between gap-4 py-3 text-sm">
                                    <dt class="text-muted-foreground">{{ label }}</dt>
                                    <dd class="min-w-0 break-words text-right font-medium text-foreground">{{ value }}</dd>
                                </div>
                                <div v-if="authors.length" class="flex items-start justify-between gap-4 py-3 text-sm">
                                    <dt class="text-muted-foreground">Authors</dt>
                                    <XRayAuthorsTicker :authors="authors" :active="activeTab === 'system-info' && (!compact || expanded)" />
                                </div>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
