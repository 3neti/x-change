<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Check, FileSpreadsheet, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { convert, destroy, update } from '@/routes/x-change/cockpit/campaigns/intakes';

type IntakeRow = {
    source_row: number;
    status: 'valid' | 'invalid';
    source: Record<string, string>;
    normalized: {
        beneficiary: Record<string, string>;
        amount_minor: number;
    } | null;
    errors: string[];
};

type CampaignIntake = {
    reference: string;
    source_name: string;
    source_format: string;
    source_headers: string[];
    source_sheet: string | null;
    row_count: number;
    mapping: Record<string, string>;
    suggestion: {
        name: string;
        profile: 'payroll' | 'assistance';
        profile_reason: string;
        fulfillment_mode: 'pay_code_distribution' | 'direct_bank_transfer';
        fulfillment_reason: string;
        needs_fulfillment_choice: boolean;
        default_wallet?: string;
        default_delivery_preference?: string;
    };
    valid_count: number;
    invalid_count: number;
    valid_principal_minor: number;
    valid_source_rows: number[];
    rows: IntakeRow[];
};

const props = defineProps<{ intake: CampaignIntake }>();

const canonicalFields = [
    ['name', 'Name'],
    ['mobile', 'Mobile'],
    ['email', 'Email'],
    ['amount', 'Amount'],
    ['institution', 'Bank Or Wallet'],
    ['bank_account', 'Account Number'],
    ['remarks', 'Remarks'],
    ['external_reference', 'Reference'],
] as const;

const reviewForm = useForm({
    profile: props.intake.suggestion.profile,
    fulfillment_mode: props.intake.suggestion.fulfillment_mode,
    mapping: { ...props.intake.mapping },
    default_wallet: props.intake.suggestion.default_wallet ?? 'GCash',
    default_delivery_preference: props.intake.suggestion.default_delivery_preference ?? 'manual',
});
const selectedRows = [...props.intake.valid_source_rows];
const conversionForm = useForm({
    name: props.intake.suggestion.name,
    profile: props.intake.suggestion.profile,
    fulfillment_mode: props.intake.suggestion.fulfillment_mode,
    included_source_rows: selectedRows,
    exclude_invalid_rows: props.intake.invalid_count > 0,
});

function refreshReview(): void {
    reviewForm.profile = conversionForm.profile;
    reviewForm.fulfillment_mode = conversionForm.fulfillment_mode;
    reviewForm.patch(update(props.intake.reference).url, { preserveScroll: true });
}

function createCampaign(): void {
    conversionForm.post(convert(props.intake.reference).url);
}

function discardReview(): void {
    if (!window.confirm('Discard this import review? No Campaign has been created yet.')) {
        return;
    }

    router.delete(destroy(props.intake.reference).url);
}

function peso(minor: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(minor / 100);
}

function selectedRowLabel(): string {
    const count = conversionForm.included_source_rows.length;

    return `${count} ${count === 1 ? 'Row' : 'Rows'}`;
}
</script>

<template>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6" data-testid="campaign-intake-dialog">
        <section class="mx-auto max-w-6xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:px-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Review Before Adding
                    </div>
                    <h2 class="mt-1 truncate text-lg font-semibold text-slate-950 dark:text-slate-50">{{ intake.source_name }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ intake.row_count }} rows · {{ intake.source_format.toUpperCase() }}<template v-if="intake.source_sheet"> · {{ intake.source_sheet }}</template></p>
                </div>
                <button type="button" aria-label="Discard import review" class="inline-flex size-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="discardReview">
                    <X class="size-5" aria-hidden="true" />
                </button>
            </header>

            <div class="grid gap-5 p-4 sm:p-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                <aside class="space-y-4">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30">
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">Ready</p>
                            <p class="mt-1 text-lg font-semibold text-emerald-950 dark:text-emerald-50">{{ intake.valid_count }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                            <p class="text-xs text-amber-700 dark:text-amber-300">Errors</p>
                            <p class="mt-1 text-lg font-semibold text-amber-950 dark:text-amber-50">{{ intake.invalid_count }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Value</p>
                            <p class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-slate-50">{{ peso(intake.valid_principal_minor) }}</p>
                        </div>
                    </div>

                    <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                        Campaign Name
                        <input v-model="conversionForm.name" maxlength="160" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50" />
                    </label>

                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium text-slate-700 dark:text-slate-200">Purpose</legend>
                        <label v-for="option in ['payroll', 'assistance']" :key="option" class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm capitalize" :class="conversionForm.profile === option ? 'border-slate-950 bg-slate-50 dark:border-slate-100 dark:bg-slate-800' : 'border-slate-200 dark:border-slate-700'">
                            <input v-model="conversionForm.profile" type="radio" :value="option" class="size-4" />
                            {{ option }}
                        </label>
                        <p class="text-xs leading-4 text-slate-500 dark:text-slate-400">{{ intake.suggestion.profile_reason }}</p>
                    </fieldset>

                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium text-slate-700 dark:text-slate-200">How Recipients Receive Funds</legend>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="conversionForm.fulfillment_mode === 'pay_code_distribution' ? 'border-slate-950 bg-slate-50 dark:border-slate-100 dark:bg-slate-800' : 'border-slate-200 dark:border-slate-700'">
                            <input v-model="conversionForm.fulfillment_mode" type="radio" value="pay_code_distribution" class="size-4" />
                            Send Pay Codes
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="conversionForm.fulfillment_mode === 'direct_bank_transfer' ? 'border-slate-950 bg-slate-50 dark:border-slate-100 dark:bg-slate-800' : 'border-slate-200 dark:border-slate-700'">
                            <input v-model="conversionForm.fulfillment_mode" type="radio" value="direct_bank_transfer" class="size-4" />
                            Transfer To Listed Accounts
                        </label>
                        <p class="text-xs leading-4 text-slate-500 dark:text-slate-400">{{ intake.suggestion.fulfillment_reason }}</p>
                    </fieldset>

                    <details class="rounded-xl border border-slate-200 dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Column Mapping</summary>
                        <div class="grid gap-2 border-t border-slate-200 p-3 dark:border-slate-700">
                            <label v-for="[key, label] in canonicalFields" :key="key" class="grid grid-cols-[7rem_1fr] items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                {{ label }}
                                <select v-model="reviewForm.mapping[key]" class="min-w-0 rounded-md border-slate-300 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    <option value="">Not Used</option>
                                    <option v-for="header in intake.source_headers" :key="header" :value="header">{{ header }}</option>
                                </select>
                            </label>
                            <button type="button" :disabled="reviewForm.processing" class="mt-1 inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700" @click="refreshReview">
                                <RefreshCw class="size-3.5" aria-hidden="true" />
                                Recheck Rows
                            </button>
                        </div>
                    </details>
                </aside>

                <div class="min-w-0">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-slate-950 dark:text-slate-50">Beneficiary Preview</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Choose valid rows to add. Scroll sideways to inspect every imported column.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ conversionForm.included_source_rows.length }} selected</span>
                    </div>
                    <div class="max-h-[32rem] overflow-auto rounded-xl border border-slate-200 dark:border-slate-700" data-testid="campaign-intake-source-table">
                        <table class="min-w-max text-left text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                                <tr>
                                    <th class="sticky left-0 z-10 w-12 bg-slate-50 px-3 py-2 dark:bg-slate-950">Add</th>
                                    <th class="min-w-20 px-3 py-2">Row</th>
                                    <th v-for="header in intake.source_headers" :key="header" class="min-w-36 max-w-64 px-3 py-2">{{ header }}</th>
                                    <th class="min-w-44 px-3 py-2">Check</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                <tr v-for="row in intake.rows" :key="row.source_row">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 dark:bg-slate-900">
                                        <input v-if="row.status === 'valid'" v-model="conversionForm.included_source_rows" type="checkbox" :value="row.source_row" class="size-4 rounded" />
                                        <AlertTriangle v-else class="size-4 text-amber-500" aria-label="Invalid row" />
                                    </td>
                                    <td class="px-3 py-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ row.source_row }}</td>
                                    <td v-for="header in intake.source_headers" :key="`${row.source_row}-${header}`" class="min-w-36 max-w-64 px-3 py-2">
                                        <span class="block truncate text-slate-800 dark:text-slate-100" :title="row.source[header] || 'Blank'">
                                            {{ row.source[header] || '—' }}
                                        </span>
                                    </td>
                                    <td class="max-w-64 px-3 py-2">
                                        <span v-if="row.status === 'valid'" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"><Check class="size-3.5" /> Ready</span>
                                        <span v-else class="text-xs leading-4 text-amber-700 dark:text-amber-300">{{ row.errors.join(' ') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <label v-if="intake.invalid_count > 0" class="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        <input v-model="conversionForm.exclude_invalid_rows" type="checkbox" class="mt-0.5 size-4 rounded" />
                        <span>Leave {{ intake.invalid_count }} invalid {{ intake.invalid_count === 1 ? 'row' : 'rows' }} out of this Campaign. The review remains auditable.</span>
                    </label>
                    <p v-if="conversionForm.errors.exclude_invalid_rows || conversionForm.errors.intake" class="mt-2 text-sm text-rose-600">{{ conversionForm.errors.exclude_invalid_rows || conversionForm.errors.intake }}</p>

                    <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-600 dark:border-rose-900 dark:text-rose-300" @click="discardReview">
                            <Trash2 class="size-4" aria-hidden="true" />
                            Discard
                        </button>
                        <button type="button" :disabled="conversionForm.processing || conversionForm.included_source_rows.length === 0" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950" @click="createCampaign">
                            <Check class="size-4" aria-hidden="true" />
                            {{ conversionForm.processing ? 'Adding…' : `Create Campaign With ${selectedRowLabel()}` }}
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
