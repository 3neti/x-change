<script setup lang="ts">
import { ref } from 'vue';
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
    preview?: {
        type?: string | null;
        href?: string | null;
        label?: string | null;
    } | null;
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

const activePreviewKey = ref<string | null>(null);

function showPreview(item: ClaimRequirementSummaryItem): void {
    if (item.preview?.type !== 'image' || !item.preview.href) {
        return;
    }

    activePreviewKey.value = item.key;
}

function hidePreview(item: ClaimRequirementSummaryItem): void {
    if (activePreviewKey.value === item.key) {
        activePreviewKey.value = null;
    }
}
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
                class="relative flex items-center justify-between gap-3 rounded-lg border bg-muted/20 px-3 py-2"
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
                <button
                    v-if="item.preview?.type === 'image' && item.preview?.href"
                    type="button"
                    class="shrink-0 rounded-full outline-none ring-ring/40 transition focus-visible:ring-2"
                    :aria-label="`Preview ${item.label}`"
                    :aria-pressed="activePreviewKey === item.key"
                    :data-testid="`claim-requirement-preview-trigger-${item.key}`"
                    @pointerdown="showPreview(item)"
                    @pointerup="hidePreview(item)"
                    @pointercancel="hidePreview(item)"
                    @pointerleave="hidePreview(item)"
                    @focus="showPreview(item)"
                    @blur="hidePreview(item)"
                >
                    <Badge :variant="toneBadgeVariant(item.tone)" class="cursor-pointer">
                        {{ humanizeRequirementStatus(item.status) }}
                    </Badge>
                </button>
                <Badge v-else :variant="toneBadgeVariant(item.tone)" class="shrink-0">
                    {{ humanizeRequirementStatus(item.status) }}
                </Badge>

                <div
                    v-if="activePreviewKey === item.key && item.preview?.href"
                    class="absolute right-2 top-full z-30 mt-2 w-[min(18rem,calc(100vw-3rem))] overflow-hidden rounded-xl border bg-background p-2 shadow-xl"
                    :data-testid="`claim-requirement-image-preview-${item.key}`"
                >
                    <img
                        :src="item.preview.href"
                        :alt="item.preview.label || `${item.label} preview`"
                        class="max-h-72 w-full rounded-lg object-contain"
                    />
                </div>
            </li>
        </ul>
    </div>
</template>
