<script setup lang="ts">
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { AlertCircle } from 'lucide-vue-next';

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

const statusLabel = computed(() => {
    const status = props.result?.status || 'unknown';

    return String(status)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
});

const badgeVariant = computed(() => {
    switch (props.result?.status) {
        case 'claimable':
        case 'partially_claimable':
            return 'default';
        case 'redeemed':
        case 'expired':
        case 'hidden':
        case 'not_found':
            return 'destructive';
        default:
            return 'secondary';
    }
});

function stageText(stage: XRayStage): string {
    const payload = stage.payload ?? {};

    return String(
        payload.message ??
            payload.body ??
            payload.content ??
            stage.message ??
            stage.body ??
            stage.title ??
            'Issuer-provided preview content is available.',
    );
}
</script>

<template>
    <div class="space-y-4" data-testid="xray-claim-preview">
        <Card v-if="loading">
            <CardContent class="py-6 text-center text-sm text-muted-foreground">
                Inspecting Pay Code...
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
                <CardContent class="space-y-3 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            Pay Code x-ray
                        </h3>
                        <Badge :variant="badgeVariant">
                            {{ statusLabel }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            result.visible
                                ? 'This Pay Code can be inspected under the current disclosure policy.'
                                : 'Details are hidden or unavailable for this viewer.'
                        }}
                    </p>
                </CardContent>

                <CardContent
                    v-if="result.disclosures?.length"
                    class="space-y-3"
                >
                    <div
                        v-for="item in result.disclosures"
                        :key="item.key"
                        class="flex items-center justify-between gap-4 rounded-lg border bg-muted/20 p-3 text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{ item.label || item.key }}
                        </span>
                        <span class="text-right font-medium">
                            {{ item.value }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="result.requirements?.length">
                <CardContent class="space-y-4 py-4">
                    <h3 class="text-base font-medium">Claim requirements</h3>
                    <ul class="space-y-3">
                        <li
                            v-for="item in result.requirements"
                            :key="item.key"
                            class="rounded-lg border bg-background p-3"
                        >
                            <p class="text-sm font-medium">
                                {{ item.label || item.key }}
                            </p>
                            <p
                                v-if="item.description"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="result.stages?.length">
                <CardContent class="space-y-3">
                    <h3 class="text-base font-medium">Issuer preview</h3>
                    <div
                        v-for="(stage, index) in result.stages"
                        :key="stage.key || index"
                        class="rounded-lg border bg-primary/5 p-3 text-sm"
                    >
                        {{ stageText(stage) }}
                    </div>
                </CardContent>
            </Card>

            <Card v-if="result.redactions?.length">
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Some Pay Code details are intentionally hidden for this
                        viewer.
                    </p>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
