<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ClipboardList, FileSpreadsheet, LockKeyhole, Plus, Send, Trash2, Upload, Users } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { destroy, show, store } from '@/routes/x-change/cockpit/campaigns';
import authorizations from '@/routes/x-change/cockpit/campaigns/authorizations';
import { store as storeIntake } from '@/routes/x-change/cockpit/campaigns/intakes';
import CockpitCampaignIntakeDialog from '../components/CockpitCampaignIntakeDialog.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderPageProps } from '../types';

type CampaignWorksheet = {
    reference: string;
    profile: 'payroll' | 'assistance';
    name: string;
    currency: string;
    status: string;
    fulfillment_mode: string;
    delivery_plan: string[];
    beneficiary_count: number;
    principal_minor: number;
    updated_at: string | null;
};

type CampaignsPageProps = CockpitHeaderPageProps & {
    worksheets: CampaignWorksheet[];
    active_intake?: Record<string, unknown>;
};

const props = defineProps<CampaignsPageProps>();

const form = useForm({
    name: '',
    profile: 'payroll',
    fulfillment_mode: 'pay_code_distribution',
    delivery_plan: ['csv'],
});
const deleteForm = useForm({});
const authorizationForm = useForm({});
const authorizingWorksheet = ref<string | null>(null);
const intakeForm = useForm<{ file: File | null }>({ file: null });
const intakeFileInput = ref<HTMLInputElement | null>(null);
const intakeDragDepth = ref(0);
const isDraggingIntake = ref(false);
const intakeFileError = ref<string | null>(null);

function uploadIntake(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        return;
    }

    submitIntakeFile(file);
}

function submitIntakeFile(file: File): void {
    intakeFileError.value = null;
    intakeForm.clearErrors('file');

    if (!/\.(csv|xlsx)$/i.test(file.name)) {
        intakeFileError.value = 'Choose a CSV or XLSX file.';

        return;
    }

    intakeForm.file = file;
    intakeForm.post(storeIntake(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => intakeForm.reset('file'),
    });
}

function openIntakeFilePicker(): void {
    if (!intakeForm.processing) {
        intakeFileInput.value?.click();
    }
}

function focusIntakeDropZone(event: MouseEvent): void {
    if (!intakeForm.processing) {
        (event.currentTarget as HTMLElement).focus();
    }
}

function beginIntakeDrag(): void {
    if (intakeForm.processing) {
        return;
    }

    intakeDragDepth.value += 1;
    isDraggingIntake.value = true;
}

function endIntakeDrag(): void {
    intakeDragDepth.value = Math.max(0, intakeDragDepth.value - 1);

    if (intakeDragDepth.value === 0) {
        isDraggingIntake.value = false;
    }
}

function submitPastedIntake(text: string): void {
    const csv = text.trim();

    if (csv === '' || !csv.includes('\n')) {
        intakeFileError.value = 'Paste CSV rows with a header and at least one beneficiary.';

        return;
    }

    submitIntakeFile(new File(
        [`${csv}\n`],
        'pasted-beneficiaries.csv',
        { type: 'text/csv' },
    ));
}

function pasteIntake(event: ClipboardEvent): void {
    if (intakeForm.processing) {
        return;
    }

    submitPastedIntake(event.clipboardData?.getData('text/plain') ?? '');
}

function isEditablePasteTarget(target: EventTarget | null): boolean {
    return target instanceof Element
        && target.closest('input, textarea, select, [contenteditable="true"], [role="textbox"]') !== null;
}

function looksLikeBeneficiaryRows(text: string): boolean {
    const header = text.trim().split(/\r?\n/, 1)[0]?.toLowerCase() ?? '';

    return /[,;\t]/.test(header)
        && /\b(amount|value)\b/.test(header)
        && /\b(name|mobile|phone|bank|account|email)\b/.test(header)
        && text.trim().includes('\n');
}

function pasteIntakeFromPage(event: ClipboardEvent): void {
    if (intakeForm.processing || event.defaultPrevented || isEditablePasteTarget(event.target)) {
        return;
    }

    const text = event.clipboardData?.getData('text/plain') ?? '';

    if (!looksLikeBeneficiaryRows(text)) {
        return;
    }

    event.preventDefault();
    submitPastedIntake(text);
}

function dropIntake(event: DragEvent): void {
    intakeDragDepth.value = 0;
    isDraggingIntake.value = false;

    if (intakeForm.processing) {
        return;
    }

    const files = Array.from(event.dataTransfer?.files ?? []);

    if (files.length > 1) {
        intakeFileError.value = 'Choose one beneficiary file at a time.';

        return;
    }

    if (files.length === 1) {
        submitIntakeFile(files[0]);

        return;
    }

    submitPastedIntake(event.dataTransfer?.getData('text/plain') ?? '');
}

onMounted(() => window.addEventListener('paste', pasteIntakeFromPage));
onBeforeUnmount(() => window.removeEventListener('paste', pasteIntakeFromPage));

function createWorksheet(): void {
    form.post(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function deleteWorksheet(worksheet: CampaignWorksheet): void {
    if (!window.confirm(`Delete “${worksheet.name}”? Its draft beneficiaries and staged imports will be permanently removed.`)) {
        return;
    }

    deleteForm.delete(destroy(worksheet.reference).url, {
        preserveScroll: true,
    });
}

function createApprovalPayCode(worksheet: CampaignWorksheet): void {
    if (worksheet.status !== 'draft' || worksheet.beneficiary_count === 0 || authorizationForm.processing) {
        return;
    }

    authorizingWorksheet.value = worksheet.reference;
    authorizationForm.post(authorizations.store(worksheet.reference), {
        preserveScroll: true,
        onFinish: () => {
            authorizingWorksheet.value = null;
        },
    });
}

function display(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function peso(minor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);
}

function activityLabel(): string {
    if (props.worksheets.length === 0) {
        return 'No worksheets';
    }

    const authorized = props.worksheets.filter((worksheet) => worksheet.status === 'authorized').length;

    if (authorized > 0) {
        return authorized === props.worksheets.length
            ? 'Authorized'
            : `${authorized} authorized`;
    }

    return 'Draft only';
}

function dateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}
</script>

<template>
    <CockpitLayout
        active-navigation="campaigns"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main class="mx-auto max-w-7xl space-y-5" data-testid="cockpit-campaigns-page">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <ClipboardList class="size-3.5" aria-hidden="true" />
                            Campaigns
                        </div>
                        <h1 class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Batch Pay Codes, Prepared With Care
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-600 dark:text-slate-300">
                            Start a private worksheet for payroll or assistance. Beneficiaries, authorization, issuance, and delivery come next—nothing is sent from a draft.
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs sm:w-72">
                        <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                            <dt class="text-slate-500 dark:text-slate-400">Worksheets</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheets.length }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                            <dt class="text-slate-500 dark:text-slate-400">Beneficiaries</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheets.reduce((total, worksheet) => total + worksheet.beneficiary_count, 0) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Your Worksheets</p>
                            <h2 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">Campaign Activity</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ activityLabel() }}</span>
                    </div>

                    <div v-if="props.worksheets.length === 0" class="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                        <Users class="size-8 text-slate-300 dark:text-slate-700" aria-hidden="true" />
                        <p class="mt-3 font-semibold text-slate-950 dark:text-slate-50">No campaign worksheets yet</p>
                        <p class="mt-1 max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400">Create one to assemble beneficiaries before any authorization or financial action.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                        <article v-for="worksheet in props.worksheets" :key="worksheet.reference" class="@container grid gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a :href="show(worksheet.reference).url" class="truncate font-semibold text-slate-950 hover:underline dark:text-slate-50">{{ worksheet.name }}</a>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ display(worksheet.profile) }}</span>
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[0.65rem] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ display(worksheet.status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ worksheet.reference }} · {{ display(worksheet.fulfillment_mode) }} · updated {{ dateTime(worksheet.updated_at) }}</p>
                            </div>
                            <div class="flex flex-col gap-3 @xl:flex-row @xl:items-center @xl:justify-between">
                                <dl class="grid w-full grid-cols-2 gap-x-4 text-left text-xs @xl:w-auto @xl:min-w-44 @xl:text-right">
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400">Beneficiaries</dt>
                                        <dd class="font-semibold text-slate-950 dark:text-slate-50">{{ worksheet.beneficiary_count }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500 dark:text-slate-400">Principal</dt>
                                        <dd class="font-semibold text-slate-950 dark:text-slate-50">{{ peso(worksheet.principal_minor) }}</dd>
                                    </div>
                                </dl>
                                <div v-if="worksheet.status === 'draft'" class="flex w-full items-center gap-2 @xl:w-auto">
                                    <button
                                        type="button"
                                        :data-testid="`campaign-activity-create-approval-${worksheet.reference}`"
                                        :disabled="worksheet.beneficiary_count === 0 || authorizationForm.processing"
                                        :title="worksheet.beneficiary_count === 0 ? 'Add at least one beneficiary first.' : 'Freeze this worksheet and create its officer Approval Pay Code.'"
                                        class="inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40 @xl:flex-none dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                                        @click="createApprovalPayCode(worksheet)"
                                    >
                                        <LockKeyhole class="size-3.5 shrink-0" aria-hidden="true" />
                                        {{ authorizingWorksheet === worksheet.reference ? 'Creating…' : 'Create Approval Pay Code' }}
                                    </button>
                                    <button
                                        type="button"
                                        :aria-label="`Delete draft ${worksheet.name}`"
                                        :disabled="deleteForm.processing"
                                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="deleteWorksheet(worksheet)"
                                    >
                                        <Trash2 class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="space-y-3">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-2">
                            <FileSpreadsheet class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                            <div>
                                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">New Campaign</p>
                                <h2 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">Import Beneficiary List</h2>
                            </div>
                        </div>
                        <p class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-300">Upload a file or paste copied CSV rows. We’ll suggest the purpose and recipient method before creating anything.</p>
                        <div
                            data-testid="campaign-import-drop-zone"
                            role="group"
                            :tabindex="intakeForm.processing ? -1 : 0"
                            :aria-disabled="intakeForm.processing"
                            aria-label="Import beneficiary list from CSV, Excel, or pasted rows"
                            class="mt-4 flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-5 py-6 text-center transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                            :class="
                                isDraggingIntake
                                    ? 'border-sky-500 bg-sky-50 ring-4 ring-sky-100 dark:border-sky-400 dark:bg-sky-950/40 dark:ring-sky-950'
                                    : 'border-slate-300 bg-slate-50/70 hover:border-sky-400 hover:bg-sky-50/60 dark:border-slate-700 dark:bg-slate-950/60 dark:hover:border-sky-500 dark:hover:bg-sky-950/30'
                            "
                            @click="focusIntakeDropZone"
                            @keydown.enter.prevent="openIntakeFilePicker"
                            @keydown.space.prevent="openIntakeFilePicker"
                            @dragenter.prevent="beginIntakeDrag"
                            @dragover.prevent
                            @dragleave.prevent="endIntakeDrag"
                            @drop.prevent="dropIntake"
                            @paste.stop.prevent="pasteIntake"
                        >
                            <span class="inline-flex size-10 items-center justify-center rounded-full bg-white text-sky-600 shadow-sm dark:bg-slate-900 dark:text-sky-300">
                                <Upload class="size-5" aria-hidden="true" />
                            </span>
                            <p class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ intakeForm.processing ? 'Inspecting Beneficiaries…' : isDraggingIntake ? 'Drop To Inspect' : 'Drop A File Or Paste Rows' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ intakeForm.processing ? 'Preparing a private review. Nothing is added yet.' : 'Press Ctrl/⌘ + V anywhere on this page' }}
                            </p>
                            <button
                                v-if="!intakeForm.processing"
                                type="button"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                                @click.stop="openIntakeFilePicker"
                            >
                                <Upload class="size-3.5" aria-hidden="true" />
                                Choose CSV Or Excel
                            </button>
                            <p class="mt-3 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">
                                CSV · XLSX · Copied Rows
                            </p>
                        </div>
                        <input
                            ref="intakeFileInput"
                            type="file"
                            accept=".csv,.xlsx"
                            class="sr-only"
                            :disabled="intakeForm.processing"
                            tabindex="-1"
                            aria-hidden="true"
                            @change="uploadIntake"
                        />
                        <p v-if="intakeFileError || intakeForm.errors.file" class="mt-2 text-xs text-rose-600 dark:text-rose-300">
                            {{ intakeFileError || intakeForm.errors.file }}
                        </p>
                    </section>

                    <details class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <summary class="cursor-pointer list-none p-4">
                            <div class="flex items-center gap-2">
                                <Plus class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                                <div>
                                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Start Blank</p>
                                    <h2 class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50">Create An Empty Campaign</h2>
                                </div>
                            </div>
                        </summary>
                <form class="border-t border-slate-200 p-4 dark:border-slate-800" @submit.prevent="createWorksheet">
                    <div class="flex items-center gap-2">
                        <Plus class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                        <div>
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">New Campaign</p>
                            <h2 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">Create A Worksheet</h2>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Campaign name
                            <input v-model="form.name" type="text" maxlength="160" placeholder="July payroll" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10" />
                            <span v-if="form.errors.name" class="text-xs font-normal text-rose-600 dark:text-rose-300">{{ form.errors.name }}</span>
                        </label>

                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Profile
                            <select v-model="form.profile" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10">
                                <option value="payroll">Payroll</option>
                                <option value="assistance">Assistance</option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Intended fulfillment
                            <select v-model="form.fulfillment_mode" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10">
                                <option value="pay_code_distribution">Pay Code distribution</option>
                                <option value="direct_bank_transfer">Direct bank transfer</option>
                            </select>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white">
                        <Send class="size-4" aria-hidden="true" />
                        {{ form.processing ? 'Creating…' : 'Create Worksheet' }}
                    </button>
                    <p class="mt-2 text-center text-xs leading-4 text-slate-500 dark:text-slate-400">No beneficiaries or funds are added yet.</p>
                </form>
                    </details>
                </div>
            </section>
        </main>
        <CockpitCampaignIntakeDialog v-if="Object.keys(props.active_intake ?? {}).length > 0" :intake="props.active_intake as never" />
    </CockpitLayout>
</template>
