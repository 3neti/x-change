<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { AlertCircle, Check, FileSpreadsheet, Trash2, Upload } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import imports from '@/routes/x-change/cockpit/campaigns/imports';

type PreviewRow = {
    source_row: number;
    status: 'valid' | 'invalid' | 'applied';
    source: Record<string, string>;
    normalized: {
        beneficiary: Record<string, string>;
        amount_minor: number;
        delivery_preference: string;
    } | null;
    errors: string[];
};

export type CampaignImport = {
    reference: string;
    status: string;
    source_format: string;
    source_sheet: string | null;
    source_headers: string[];
    row_count: number;
    valid_count: number;
    unapplied_valid_count: number;
    invalid_count: number;
    valid_principal_minor: number;
    validation_errors: { row: number; messages: string[] }[];
    mapping: Record<string, string>;
    default_wallet: string;
    default_delivery_preference: string;
    preview: PreviewRow[];
};

const props = defineProps<{
    worksheetReference: string;
    fulfillmentMode: string;
    imports: CampaignImport[];
}>();

const activeImport = computed(() => props.imports.find((item) => ['staged', 'applied_with_errors'].includes(item.status)) ?? null);
const uploadForm = useForm({ file: null as File | null });
const mappingForm = useForm({
    mapping: {} as Record<string, string>,
    default_wallet: 'GCash',
    default_delivery_preference: 'manual',
});
const actionForm = useForm({});

const mappingFields = computed(() => {
    const fields = [
        ['amount', 'Amount'],
        ['name', 'Name'],
        ['mobile', 'Mobile'],
        ['email', 'Email'],
    ];

    if (props.fulfillmentMode === 'direct_bank_transfer') {
        fields.push(['institution', 'Bank Or Wallet'], ['bank_account', 'Account Number']);
    }

    fields.push(['remarks', 'Remarks'], ['external_reference', 'Reference'], ['delivery_preference', 'Delivery']);

    return fields;
});

watch(activeImport, (item) => {
    if (!item) return;
    mappingForm.mapping = { ...item.mapping };
    mappingForm.default_wallet = item.default_wallet;
    mappingForm.default_delivery_preference = item.default_delivery_preference;
}, { immediate: true });

const peso = (minor: number): string => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(minor / 100);

const stage = (): void => uploadForm.post(imports.store(props.worksheetReference).url, {
    preserveScroll: true,
    onSuccess: () => uploadForm.reset(),
});

const updateMapping = (): void => {
    if (!activeImport.value) return;
    mappingForm.patch(imports.mapping.update({
        worksheet: props.worksheetReference,
        import: activeImport.value.reference,
    }).url, { preserveScroll: true });
};

const apply = (): void => {
    if (!activeImport.value) return;
    actionForm.post(imports.apply({
        worksheet: props.worksheetReference,
        import: activeImport.value.reference,
    }).url, { preserveScroll: true });
};

const discard = (): void => {
    if (!activeImport.value) return;
    router.delete(imports.destroy({
        worksheet: props.worksheetReference,
        import: activeImport.value.reference,
    }).url, { preserveScroll: true });
};

const rowRecipient = (row: PreviewRow): string => {
    const beneficiary = row.normalized?.beneficiary;
    if (beneficiary) {
        return beneficiary.name || beneficiary.mobile || beneficiary.bank_account || 'Recipient';
    }

    return Object.values(row.source).find((value) => value !== '') ?? 'Incomplete row';
};

const rowDetail = (row: PreviewRow): string => {
    const beneficiary = row.normalized?.beneficiary;
    if (!beneficiary) return row.errors.join(' ');

    return [beneficiary.institution, beneficiary.mobile || beneficiary.bank_account, row.normalized.delivery_preference]
        .filter(Boolean)
        .join(' · ');
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" data-testid="campaign-import-workspace">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="flex items-center gap-2">
                <FileSpreadsheet class="size-4 text-slate-500" />
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Beneficiary Import</p>
                    <h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Review Before Adding</h2>
                </div>
            </div>
            <form class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="stage">
                <label class="min-w-0 cursor-pointer rounded-xl border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:border-slate-500 dark:border-slate-700 dark:text-slate-300">
                    <span class="inline-flex max-w-64 items-center gap-2 truncate"><Upload class="size-4 shrink-0" /> {{ uploadForm.file?.name ?? 'Choose CSV Or XLSX' }}</span>
                    <input accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" type="file" class="sr-only" @input="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                </label>
                <button type="submit" :disabled="!uploadForm.file || uploadForm.processing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950">
                    {{ uploadForm.processing ? 'Reading…' : 'Preview File' }}
                </button>
            </form>
        </div>

        <div v-if="uploadForm.progress" class="h-1 bg-slate-100 dark:bg-slate-800">
            <div class="h-full bg-emerald-500 transition-all" :style="{ width: `${uploadForm.progress.percentage}%` }" />
        </div>
        <p v-if="uploadForm.errors.file" class="px-4 py-3 text-sm text-rose-600 dark:text-rose-300">{{ uploadForm.errors.file }}</p>

        <div v-if="!activeImport" class="grid min-h-44 place-items-center px-4 py-8 text-center">
            <div>
                <Upload class="mx-auto size-7 text-slate-300 dark:text-slate-700" />
                <p class="mt-3 font-semibold text-slate-950 dark:text-slate-50">Bring Your Existing List</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Two columns are enough: Mobile and Amount.</p>
            </div>
        </div>

        <div v-else class="grid xl:grid-cols-[18rem_minmax(0,1fr)]">
            <form class="border-b border-slate-200 p-4 xl:border-r xl:border-b-0 dark:border-slate-800" @submit.prevent="updateMapping">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-slate-50">{{ activeImport.source_format.toUpperCase() }} Columns</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ activeImport.row_count }} rows<template v-if="activeImport.source_sheet"> · {{ activeImport.source_sheet }}</template></p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Staged</span>
                </div>

                <div class="mt-4 grid gap-2">
                    <label v-for="[field, label] in mappingFields" :key="field" class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                        {{ label }}
                        <select v-model="mappingForm.mapping[field]" class="min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">Not Used</option>
                            <option v-for="header in activeImport.source_headers" :key="header" :value="header">{{ header }}</option>
                        </select>
                    </label>
                    <label v-if="fulfillmentMode === 'direct_bank_transfer'" class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                        Mobile Default
                        <input v-model="mappingForm.default_wallet" class="min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                    </label>
                    <label class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                        Delivery Default
                        <select v-model="mappingForm.default_delivery_preference" class="min-w-0 rounded-lg border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="manual">Download Only</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </label>
                </div>
                <button type="submit" :disabled="mappingForm.processing" class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200">
                    {{ mappingForm.processing ? 'Checking…' : 'Update Mapping' }}
                </button>
                <p v-if="mappingForm.errors.mapping" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ mappingForm.errors.mapping }}</p>
            </form>

            <div class="min-w-0">
                <div class="grid grid-cols-3 gap-px border-b border-slate-200 bg-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    <div class="bg-white px-3 py-2.5 dark:bg-slate-900"><p class="text-xs text-slate-500 dark:text-slate-400">Ready</p><p class="mt-0.5 font-semibold text-emerald-700 dark:text-emerald-300">{{ activeImport.unapplied_valid_count }}</p></div>
                    <div class="bg-white px-3 py-2.5 dark:bg-slate-900"><p class="text-xs text-slate-500 dark:text-slate-400">Needs Attention</p><p class="mt-0.5 font-semibold" :class="activeImport.invalid_count ? 'text-rose-600 dark:text-rose-300' : 'text-slate-950 dark:text-white'">{{ activeImport.invalid_count }}</p></div>
                    <div class="bg-white px-3 py-2.5 dark:bg-slate-900"><p class="text-xs text-slate-500 dark:text-slate-400">Ready Value</p><p class="mt-0.5 font-semibold text-slate-950 dark:text-white">{{ peso(activeImport.valid_principal_minor) }}</p></div>
                </div>

                <div class="max-h-80 overflow-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                            <tr><th class="px-3 py-2 font-medium">Row</th><th class="px-3 py-2 font-medium">Recipient</th><th class="px-3 py-2 font-medium">Amount</th><th class="px-3 py-2 font-medium">Check</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="row in activeImport.preview" :key="row.source_row">
                                <td class="px-3 py-2 text-xs text-slate-500">{{ row.source_row }}</td>
                                <td class="px-3 py-2"><p class="font-medium text-slate-950 dark:text-white">{{ rowRecipient(row) }}</p><p class="mt-0.5 max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ rowDetail(row) }}</p></td>
                                <td class="px-3 py-2 font-medium text-slate-950 dark:text-white">{{ row.normalized ? peso(row.normalized.amount_minor) : '—' }}</td>
                                <td class="px-3 py-2"><span v-if="row.status === 'valid'" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"><Check class="size-3.5" /> Ready</span><span v-else class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-300"><AlertCircle class="size-3.5" /> Fix Row</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Invalid rows stay staged for correction. Nothing is silently skipped.</p>
                    <div class="flex gap-2">
                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300" @click="discard"><Trash2 class="size-3.5" /> Discard</button>
                        <button type="button" :disabled="activeImport.unapplied_valid_count === 0 || actionForm.processing" class="inline-flex items-center gap-1 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950" @click="apply"><Check class="size-3.5" /> Add {{ activeImport.unapplied_valid_count }} Valid Beneficiaries</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
