<script setup lang="ts">
import { Landmark, Users } from 'lucide-vue-next';

export type CampaignWorksheetBeneficiaryRow = {
    reference: string;
    ordinal: number;
    beneficiary: Record<string, string>;
    amount_minor: number;
    delivery_preference: string;
    status: string;
};

defineProps<{
    rows: CampaignWorksheetBeneficiaryRow[];
    draft: boolean;
}>();

const peso = (minor: number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);
</script>

<template>
    <div
        v-if="rows.length === 0"
        class="flex min-h-64 flex-col items-center justify-center text-center"
    >
        <Users class="size-8 text-slate-300 dark:text-slate-700" />
        <p class="mt-3 font-semibold text-slate-950 dark:text-slate-50">
            No beneficiaries yet
        </p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{
                draft
                    ? 'Add the first recipient from the form.'
                    : 'This authorized worksheet has no beneficiaries.'
            }}
        </p>
    </div>

    <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
        <article
            v-for="row in rows"
            :key="row.reference"
            :data-testid="`campaign-worksheet-row-${row.reference}`"
            class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
        >
            <div class="min-w-0">
                <p class="truncate font-semibold text-slate-950 dark:text-slate-50">
                    {{
                        row.beneficiary.name ||
                        row.beneficiary.mobile ||
                        row.beneficiary.bank_account ||
                        'Beneficiary'
                    }}
                </p>
                <div
                    class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400"
                >
                    <span v-if="row.beneficiary.mobile">
                        {{ row.beneficiary.mobile }}
                    </span>
                    <span
                        v-if="row.beneficiary.bank_account"
                        :data-testid="`campaign-worksheet-bank-destination-${row.reference}`"
                        class="inline-flex min-w-0 items-center gap-1.5"
                    >
                        <Landmark class="size-3.5 shrink-0" />
                        <span
                            class="font-medium text-slate-600 dark:text-slate-300"
                        >
                            {{
                                row.beneficiary.institution ||
                                row.beneficiary.bank_code ||
                                'Bank'
                            }}
                        </span>
                        <span
                            class="font-mono text-slate-700 dark:text-slate-200"
                        >
                            {{ row.beneficiary.bank_account }}
                        </span>
                    </span>
                    <span class="capitalize">{{ row.delivery_preference }}</span>
                </div>
            </div>
            <p class="font-semibold text-slate-950 dark:text-slate-50">
                {{ peso(row.amount_minor) }}
            </p>
        </article>
    </div>
</template>
