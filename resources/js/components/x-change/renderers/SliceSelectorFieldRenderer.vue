<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import type { FormFlowField } from '../formFlow';
import ReadonlyFieldRendererShell from './ReadonlyFieldRendererShell.vue';

type SliceOption = {
    id: string;
    amount: number;
    description: string;
    tag?: string | null;
    claim_on?: string | null;
    claim_by?: string | null;
    available?: boolean;
    disabled?: boolean;
    disabled_reason?: string | null;
};

const props = defineProps<{
    field: FormFlowField;
    value?: unknown;
}>();

const emit = defineEmits<{
    'update:value': [value: string[]];
}>();

const selectedIds = computed<string[]>(() => {
    if (Array.isArray(props.value)) {
        return props.value.map(String);
    }

    if (typeof props.value === 'string' && props.value.trim() !== '') {
        return props.value.split(',').map((id) => id.trim()).filter(Boolean);
    }

    return [];
});

const options = computed<SliceOption[]>(() =>
    (Array.isArray(props.field.options) ? props.field.options : [])
        .map((option): SliceOption | null => {
            if (!option || typeof option !== 'object') {
                return null;
            }

            const record = option as Record<string, unknown>;
            const id = String(record.id ?? '');

            if (id === '') {
                return null;
            }

            return {
                id,
                amount: Number(record.amount ?? 0),
                description: String(record.description ?? id),
                tag: typeof record.tag === 'string' ? record.tag : null,
                claim_on: typeof record.claim_on === 'string' ? record.claim_on : null,
                claim_by: typeof record.claim_by === 'string' ? record.claim_by : null,
                available: record.available === true,
                disabled: record.disabled === true,
                disabled_reason: typeof record.disabled_reason === 'string' ? record.disabled_reason : null,
            };
        })
        .filter((option): option is SliceOption => option !== null)
);

const availableOptions = computed(() =>
    options.value.filter((option) => option.disabled !== true && option.available !== false)
);

const selectedTotal = computed(() =>
    options.value
        .filter((option) => selectedIds.value.includes(option.id))
        .reduce((total, option) => total + option.amount, 0)
);

const allAvailableSelected = computed(() =>
    availableOptions.value.length > 0
    && availableOptions.value.every((option) => selectedIds.value.includes(option.id))
);

function toggleSlice(id: string, checked: boolean | 'indeterminate'): void {
    const current = new Set(selectedIds.value);

    if (checked === true) {
        current.add(id);
    } else {
        current.delete(id);
    }

    emit('update:value', Array.from(current));
}

function toggleAll(): void {
    if (allAvailableSelected.value) {
        emit('update:value', []);

        return;
    }

    emit('update:value', availableOptions.value.map((option) => option.id));
}
</script>

<template>
    <div class="space-y-3" data-testid="slice-selector-field-renderer">
        <ReadonlyFieldRendererShell
            :field="props.field"
            :value="selectedIds.join(', ')"
            kind="slice selector field"
            test-id="slice-selector-field-renderer-shell"
        />

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/20 p-3">
            <div class="space-y-1">
                <p class="text-sm font-medium">{{ props.field.label ?? 'Slices to Redeem' }}</p>
                <p class="text-xs text-muted-foreground">
                    Selected total: ₱{{ selectedTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </p>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="availableOptions.length === 0"
                @click="toggleAll"
            >
                {{ allAvailableSelected ? 'Clear all' : 'Select all' }}
            </Button>
        </div>

        <div class="space-y-2">
            <label
                v-for="option in options"
                :key="option.id"
                class="flex items-start gap-3 rounded-lg border bg-background p-3"
                :class="option.disabled ? 'opacity-60' : ''"
            >
                <Checkbox
                    :checked="selectedIds.includes(option.id)"
                    :disabled="option.disabled"
                    @update:model-value="toggleSlice(option.id, $event)"
                />

                <span class="min-w-0 flex-1 space-y-1">
                    <span class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ option.description }}</span>
                        <span class="font-semibold">₱{{ option.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                    </span>

                    <span class="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <Badge v-if="option.tag" variant="secondary">{{ option.tag }}</Badge>
                        <span v-if="option.claim_on">From {{ option.claim_on }}</span>
                        <span v-if="option.claim_by">Until {{ option.claim_by }}</span>
                    </span>

                    <span v-if="option.disabled_reason" class="block text-xs text-destructive">
                        {{ option.disabled_reason }}
                    </span>
                </span>
            </label>
        </div>
    </div>
</template>
