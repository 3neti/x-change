<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus, Trash2 } from 'lucide-vue-next';

interface PayCodeNamedSlice {
    id?: string | null;
    amount?: number | string | null;
    description?: string | null;
    tag?: string | null;
    claim_on?: string | null;
    claim_by?: string | null;
}

const props = defineProps<{
    enabled?: boolean;
    amount?: number | string | null;
    slices?: PayCodeNamedSlice[];
}>();

const emit = defineEmits<{
    'update:enabled': [value: boolean];
    'update:slices': [value: PayCodeNamedSlice[]];
}>();

const voucherAmount = computed(() => Number(props.amount || 0));
const slices = computed(() => props.slices ?? []);
const sliceTotal = computed(() =>
    slices.value.reduce((total, slice) => total + Number(slice.amount || 0), 0),
);
const variance = computed(() =>
    Number((voucherAmount.value - sliceTotal.value).toFixed(2)),
);
const hasBalancedSlices = computed(
    () =>
        !props.enabled ||
        (voucherAmount.value > 0 && Math.abs(variance.value) < 0.01),
);

function nextSliceId(index: number): string {
    return `slice_${index + 1}`;
}

function emitSlices(nextSlices: PayCodeNamedSlice[]): void {
    emit(
        'update:slices',
        nextSlices.map((slice, index) => ({
            ...slice,
            id: slice.id || nextSliceId(index),
        })),
    );
}

function toggleEnabled(value: boolean | 'indeterminate'): void {
    emit('update:enabled', value === true);
}

function updateSlice(
    index: number,
    key: keyof PayCodeNamedSlice,
    value: unknown,
): void {
    const nextSlices = slices.value.map((slice, sliceIndex) =>
        sliceIndex === index ? { ...slice, [key]: value } : slice,
    );

    emitSlices(nextSlices);
}

function addSlice(): void {
    emitSlices([
        ...slices.value,
        {
            id: nextSliceId(slices.value.length),
            amount: '',
            description: '',
            tag: '',
            claim_on: '',
            claim_by: '',
        },
    ]);
}

function removeSlice(index: number): void {
    emitSlices(slices.value.filter((_, sliceIndex) => sliceIndex !== index));
}

function useWholeAmount(): void {
    emitSlices([
        {
            id: 'slice_1',
            amount: voucherAmount.value || '',
            description: 'Whole amount',
            tag: '',
            claim_on: '',
            claim_by: '',
        },
    ]);
}

function splitEvenly(): void {
    const count = Math.max(1, slices.value.length || 2);
    const base = Math.floor((voucherAmount.value * 100) / count);
    let remaining = Math.round(voucherAmount.value * 100) - base * count;

    emitSlices(
        Array.from({ length: count }, (_, index) => {
            const extra = remaining > 0 ? 1 : 0;
            remaining -= extra;

            return {
                id: nextSliceId(index),
                amount: ((base + extra) / 100).toFixed(2),
                description:
                    count === 1 ? 'Whole amount' : `Slice ${index + 1}`,
                tag: '',
                claim_on: '',
                claim_by: '',
            };
        }),
    );
}
</script>

<template>
    <div class="space-y-4 rounded-xl border bg-muted/10 p-4">
        <label class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="text-sm font-medium">Named claim slices</p>
                <p class="text-xs text-muted-foreground">
                    Define exact claim units with issuer-facing descriptions and
                    claim windows.
                </p>
            </div>

            <Checkbox
                :checked="props.enabled === true"
                @update:model-value="toggleEnabled"
            />
        </label>

        <div v-if="props.enabled" class="space-y-4">
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3"
            >
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="secondary">
                        Voucher ₱{{
                            voucherAmount.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })
                        }}
                    </Badge>
                    <Badge
                        :variant="hasBalancedSlices ? 'default' : 'destructive'"
                    >
                        Slices ₱{{
                            sliceTotal.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })
                        }}
                    </Badge>
                    <span
                        v-if="!hasBalancedSlices"
                        class="text-xs text-destructive"
                    >
                        Difference ₱{{
                            Math.abs(variance).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })
                        }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="useWholeAmount"
                    >
                        Whole amount
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="splitEvenly"
                    >
                        Split evenly
                    </Button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(slice, index) in slices"
                    :key="slice.id || index"
                    class="space-y-3 rounded-lg border bg-background p-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium">Slice {{ index + 1 }}</p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="slices.length <= 1"
                            @click="removeSlice(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                            Remove
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label :for="`slice-${index}-amount`">Amount</Label>
                            <Input
                                :id="`slice-${index}-amount`"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :model-value="slice.amount ?? ''"
                                @update:model-value="
                                    updateSlice(index, 'amount', $event)
                                "
                            />
                        </div>

                        <div class="space-y-2">
                            <Label :for="`slice-${index}-description`"
                                >Description</Label
                            >
                            <Input
                                :id="`slice-${index}-description`"
                                :placeholder="
                                    slices.length === 1
                                        ? 'Whole amount'
                                        : `Slice ${index + 1}`
                                "
                                :model-value="slice.description ?? ''"
                                @update:model-value="
                                    updateSlice(index, 'description', $event)
                                "
                            />
                        </div>

                        <div class="space-y-2">
                            <Label :for="`slice-${index}-tag`">Tag</Label>
                            <Input
                                :id="`slice-${index}-tag`"
                                placeholder="product, service, milestone"
                                :model-value="slice.tag ?? ''"
                                @update:model-value="
                                    updateSlice(index, 'tag', $event)
                                "
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label :for="`slice-${index}-claim-on`"
                                    >Claim on</Label
                                >
                                <Input
                                    :id="`slice-${index}-claim-on`"
                                    type="datetime-local"
                                    :model-value="slice.claim_on ?? ''"
                                    @update:model-value="
                                        updateSlice(index, 'claim_on', $event)
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <Label :for="`slice-${index}-claim-by`"
                                    >Claim by</Label
                                >
                                <Input
                                    :id="`slice-${index}-claim-by`"
                                    type="datetime-local"
                                    :model-value="slice.claim_by ?? ''"
                                    @update:model-value="
                                        updateSlice(index, 'claim_by', $event)
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                class="w-full"
                @click="addSlice"
            >
                <Plus class="mr-2 h-4 w-4" />
                Add slice
            </Button>
        </div>
    </div>
</template>
