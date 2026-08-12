<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    humanizeRequirementStatus,
    requirementIcon,
    toneBadgeVariant,
} from '@/components/x-change/claimSurfaceViewModel';

export interface ClaimRequirementSummaryItem {
    key: string;
    label: string;
    status: string;
    tone?: string | null;
    description?: string | null;
}

/**
 * Summary-only display: every item here is already a safe status/tone/label
 * triple produced by `ClaimRequirementSummaryBuilder` -- never a raw
 * evidence value. This component must never be extended to render a
 * `description`/raw payload for capture-style requirements.
 */
defineProps<{
    items: ClaimRequirementSummaryItem[];
}>();
</script>

<template>
    <div class="space-y-2" data-testid="claim-requirement-summary">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
            Claim requirements
        </p>

        <ul class="space-y-1.5">
            <li
                v-for="item in items"
                :key="item.key"
                class="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 px-3 py-2"
                :data-testid="`claim-requirement-summary-item-${item.key}`"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <component
                        :is="requirementIcon(item.key)"
                        v-if="requirementIcon(item.key)"
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="truncate text-sm font-medium text-foreground">
                        {{ item.label }}
                    </span>
                </div>
                <Badge :variant="toneBadgeVariant(item.tone)" class="shrink-0">
                    {{ humanizeRequirementStatus(item.status) }}
                </Badge>
            </li>
        </ul>
    </div>
</template>
