<script setup lang="ts">
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { AlertCircle } from 'lucide-vue-next';
import {
    resolveXRayRequirementViewModel,
    resolveXRayStatusViewModel,
} from '@/components/x-change/xrayClaimPreviewViewModel';

interface XRayDisclosure {
    key: string;
    label?: string | null;
    value?: unknown;
}

interface XRayRequirement {
    key: string;
    label?: string | null;
    description?: string | null;
}

interface XRayStage {
    type?: string | null;
    key?: string | null;
    payload?: Record<string, unknown> | null;
    message?: string | null;
    body?: string | null;
    title?: string | null;
}

interface XRayResult {
    visible?: boolean;
    status?: string | null;
    disclosures?: XRayDisclosure[];
    requirements?: XRayRequirement[];
    stages?: XRayStage[];
    redactions?: Record<string, unknown>[];
    warnings?: string[];
}

const props = defineProps<{
    result?: XRayResult | null;
    loading?: boolean;
    error?: string | null;
}>();

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

// Anything disclosed besides the status itself (e.g. amount/issuer for a
// non-guest audience). Guests typically see nothing here, since the status
// is already shown in the friendly header above.
const extraDisclosures = computed(() =>
    (props.result?.disclosures ?? []).filter((item) => item.key !== 'status'),
);

const stages = computed(() => props.result?.stages ?? []);
const isPlainClaimable = computed(
    () => props.result?.visible !== false && props.result?.status === 'claimable',
);
const headingLabel = computed(() =>
    isPlainClaimable.value ? 'Pay Code verified' : 'Pay Code preview',
);
const showStatusBadge = computed(() => !isPlainClaimable.value);

function stageText(stage: XRayStage): string {
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

                    <!-- Other disclosed details (non-guest audiences only) -->
                    <div v-if="extraDisclosures.length" class="space-y-2">
                        <div
                            v-for="item in extraDisclosures"
                            :key="item.key"
                            class="flex items-center justify-between gap-4 text-sm"
                        >
                            <span class="text-muted-foreground">
                                {{ item.label || item.key }}
                            </span>
                            <span class="text-right font-medium">
                                {{ item.value }}
                            </span>
                        </div>
                    </div>

                    <!-- Requirements -->
                    <div v-if="requirements.length" class="space-y-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground"
                        >
                            What you'll need
                        </p>
                        <ul class="flex flex-wrap gap-2">
                            <li
                                v-for="item in requirements"
                                :key="item.key"
                                class="inline-flex max-w-full items-center gap-1.5 rounded-full border bg-muted/30 px-3 py-1 text-xs font-medium text-foreground"
                                :title="item.description || undefined"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ item.label }}</span>
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
                </CardContent>
            </Card>
        </template>
    </div>
</template>
