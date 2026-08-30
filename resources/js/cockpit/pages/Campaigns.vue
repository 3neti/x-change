<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    ClipboardList,
    FileSpreadsheet,
    HandCoins,
    LockKeyhole,
    Plus,
    Send,
    Trash2,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { destroy, show, store } from '@/routes/x-change/cockpit/campaigns';
import authorizations from '@/routes/x-change/cockpit/campaigns/authorizations';
import { store as storeIntake } from '@/routes/x-change/cockpit/campaigns/intakes';
import CockpitCampaignIntakeDialog from '../components/CockpitCampaignIntakeDialog.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import { formatAbsoluteTime, formatRelativeTime } from '../utils/dateTime';
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

type CampaignFlavor = CampaignWorksheet['profile'];

const campaignFlavors: CampaignFlavor[] = ['payroll', 'assistance'];
const activeFlavor = ref<CampaignFlavor>('payroll');
const flavorCopy: Record<
    CampaignFlavor,
    {
        eyebrow: string;
        title: string;
        description: string;
        listTitle: string;
        people: string;
        person: string;
        total: string;
        emptyTitle: string;
        emptyDescription: string;
        importTitle: string;
        importDescription: string;
        emptyBatchTitle: string;
        namePlaceholder: string;
    }
> = {
    payroll: {
        eyebrow: 'Payroll',
        title: 'Payroll Payouts',
        description: 'Prepare and approve net-pay payouts for your team.',
        listTitle: 'Payroll Runs',
        people: 'Employees',
        person: 'employee',
        total: 'Net Payroll',
        emptyTitle: 'No payroll runs yet',
        emptyDescription:
            'Upload an employee list or start an empty payroll run.',
        importTitle: 'Upload Payroll List',
        importDescription:
            'Add CSV or XLSX employee data, or paste copied rows. Review everything before creating the run.',
        emptyBatchTitle: 'Start Empty Payroll',
        namePlaceholder: 'August payroll',
    },
    assistance: {
        eyebrow: 'Ayuda',
        title: 'Ayuda Distribution',
        description:
            'Prepare and approve assistance for eligible beneficiaries.',
        listTitle: 'Distribution Batches',
        people: 'Beneficiaries',
        person: 'beneficiary',
        total: 'Assistance',
        emptyTitle: 'No ayuda batches yet',
        emptyDescription:
            'Upload a beneficiary list or start an empty distribution batch.',
        importTitle: 'Upload Beneficiary List',
        importDescription:
            'Add CSV or XLSX beneficiary data, or paste copied rows. Review everything before creating the batch.',
        emptyBatchTitle: 'Start Empty Distribution',
        namePlaceholder: 'August assistance',
    },
};
const activeCopy = computed(() => flavorCopy[activeFlavor.value]);
const visibleWorksheets = computed(() =>
    props.worksheets.filter(
        (worksheet) => worksheet.profile === activeFlavor.value,
    ),
);
const visiblePeopleCount = computed(() =>
    visibleWorksheets.value.reduce(
        (total, worksheet) => total + worksheet.beneficiary_count,
        0,
    ),
);

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

watch(activeFlavor, (profile) => {
    form.profile = profile;
});

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
    intakeForm.post(storeIntake().url, {
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
        intakeFileError.value =
            'Paste rows with a header and at least one recipient.';

        return;
    }

    submitIntakeFile(
        new File([`${csv}\n`], 'pasted-recipients.csv', {
            type: 'text/csv',
        }),
    );
}

function pasteIntake(event: ClipboardEvent): void {
    if (intakeForm.processing) {
        return;
    }

    submitPastedIntake(event.clipboardData?.getData('text/plain') ?? '');
}

function isEditablePasteTarget(target: EventTarget | null): boolean {
    return (
        target instanceof Element &&
        target.closest(
            'input, textarea, select, [contenteditable="true"], [role="textbox"]',
        ) !== null
    );
}

function looksLikeBeneficiaryRows(text: string): boolean {
    const header = text.trim().split(/\r?\n/, 1)[0]?.toLowerCase() ?? '';

    return (
        /[,;\t]/.test(header) &&
        /\b(amount|value)\b/.test(header) &&
        /\b(name|mobile|phone|bank|account|email)\b/.test(header) &&
        text.trim().includes('\n')
    );
}

function pasteIntakeFromPage(event: ClipboardEvent): void {
    if (
        intakeForm.processing ||
        event.defaultPrevented ||
        isEditablePasteTarget(event.target)
    ) {
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
        intakeFileError.value = 'Choose one recipient file at a time.';

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
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function deleteWorksheet(worksheet: CampaignWorksheet): void {
    if (
        !window.confirm(
            `Delete “${worksheet.name}”? Its draft beneficiaries and staged imports will be permanently removed.`,
        )
    ) {
        return;
    }

    deleteForm.delete(destroy(worksheet.reference).url, {
        preserveScroll: true,
    });
}

function createApprovalPayCode(worksheet: CampaignWorksheet): void {
    if (
        worksheet.status !== 'draft' ||
        worksheet.beneficiary_count === 0 ||
        authorizationForm.processing
    ) {
        return;
    }

    authorizingWorksheet.value = worksheet.reference;
    authorizationForm.post(authorizations.store(worksheet.reference).url, {
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
    if (visibleWorksheets.value.length === 0) {
        return 'No batches';
    }

    const authorized = visibleWorksheets.value.filter(
        (worksheet) => worksheet.status === 'authorized',
    ).length;

    if (authorized > 0) {
        return authorized === visibleWorksheets.value.length
            ? 'Authorized'
            : `${authorized} authorized`;
    }

    return 'Draft only';
}

function statusClasses(status: string): string {
    if (status === 'authorized' || status === 'completed') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    }

    if (status === 'awaiting_officer' || status === 'awaiting_authorization') {
        return 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300';
    }

    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
}

function batchActionLabel(worksheet: CampaignWorksheet): string {
    if (worksheet.status === 'draft') {
        return worksheet.beneficiary_count > 0
            ? 'Request Approval'
            : 'Continue';
    }

    if (
        worksheet.status === 'awaiting_officer' ||
        worksheet.status === 'awaiting_authorization'
    ) {
        return 'View Approval';
    }

    if (worksheet.status === 'authorized') {
        return 'Open Batch';
    }

    return 'View Results';
}

const updatedRelativeTime = (value: string | null): string =>
    formatRelativeTime(value, Date.now()) ?? 'Time unavailable';
</script>

<template>
    <CockpitLayout
        active-navigation="campaigns"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-campaigns-page"
        >
            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-campaigns-orientation"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <div
                            class="flex items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            <ClipboardList
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ activeCopy.eyebrow }}
                        </div>
                        <h1
                            class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ activeCopy.title }}
                        </h1>
                        <p
                            class="mt-1 max-w-2xl text-sm leading-5 text-slate-600 dark:text-slate-300"
                        >
                            {{ activeCopy.description }}
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs sm:w-72">
                        <div
                            class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                        >
                            <dt class="text-slate-500 dark:text-slate-400">
                                {{
                                    activeFlavor === 'payroll'
                                        ? 'Runs'
                                        : 'Batches'
                                }}
                            </dt>
                            <dd
                                class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ visibleWorksheets.length }}
                            </dd>
                        </div>
                        <div
                            class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                        >
                            <dt class="text-slate-500 dark:text-slate-400">
                                {{ activeCopy.people }}
                            </dt>
                            <dd
                                class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ visiblePeopleCount }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div
                    class="mt-4 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-950"
                    role="group"
                    aria-label="Campaign flavor"
                    data-testid="campaign-flavor-switch"
                >
                    <button
                        v-for="flavor in campaignFlavors"
                        :key="flavor"
                        type="button"
                        :aria-pressed="activeFlavor === flavor"
                        :data-testid="`campaign-flavor-${flavor === 'assistance' ? 'ayuda' : flavor}`"
                        :class="
                            activeFlavor === flavor
                                ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-800 dark:text-white'
                                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        "
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-3 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                        @click="activeFlavor = flavor"
                    >
                        <BriefcaseBusiness
                            v-if="flavor === 'payroll'"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <HandCoins
                            v-else
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ flavor === 'payroll' ? 'Payroll' : 'Ayuda' }}
                    </button>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                Your Batches
                            </p>
                            <h2
                                class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ activeCopy.listTitle }}
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >{{ activityLabel() }}</span
                        >
                    </div>

                    <div
                        v-if="visibleWorksheets.length === 0"
                        class="flex min-h-64 flex-col items-center justify-center px-6 text-center"
                    >
                        <Users
                            class="size-8 text-slate-300 dark:text-slate-700"
                            aria-hidden="true"
                        />
                        <p
                            class="mt-3 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ activeCopy.emptyTitle }}
                        </p>
                        <p
                            class="mt-1 max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400"
                        >
                            {{ activeCopy.emptyDescription }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="divide-y divide-slate-200 dark:divide-slate-800"
                    >
                        <article
                            v-for="worksheet in visibleWorksheets"
                            :key="worksheet.reference"
                            class="@container @3xl:grid-cols-[minmax(0,1fr)_auto] @3xl:items-center grid gap-3 px-4 py-3"
                            :title="worksheet.reference"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        :href="show(worksheet.reference).url"
                                        class="truncate font-semibold text-slate-950 hover:underline dark:text-slate-50"
                                        >{{ worksheet.name }}</a
                                    >
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        >{{ display(worksheet.profile) }}</span
                                    >
                                    <span
                                        :class="statusClasses(worksheet.status)"
                                        class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold"
                                        >{{ display(worksheet.status) }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ display(worksheet.fulfillment_mode) }} ·
                                    updated
                                    <time
                                        :datetime="
                                            worksheet.updated_at ?? undefined
                                        "
                                        :title="
                                            formatAbsoluteTime(
                                                worksheet.updated_at,
                                            )
                                        "
                                        >{{
                                            updatedRelativeTime(
                                                worksheet.updated_at,
                                            )
                                        }}</time
                                    >
                                </p>
                            </div>
                            <div
                                class="@xl:flex-row @xl:items-center @xl:justify-end flex flex-col gap-3"
                            >
                                <dl
                                    class="@xl:w-auto @xl:min-w-44 @xl:text-right grid w-full grid-cols-2 gap-x-5 text-left text-xs"
                                >
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ activeCopy.people }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ worksheet.beneficiary_count }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ activeCopy.total }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                peso(worksheet.principal_minor)
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <div
                                    class="@xl:w-auto flex w-full items-center gap-2"
                                >
                                    <button
                                        v-if="
                                            worksheet.status === 'draft' &&
                                            worksheet.beneficiary_count > 0
                                        "
                                        type="button"
                                        :data-testid="`campaign-activity-create-approval-${worksheet.reference}`"
                                        :disabled="authorizationForm.processing"
                                        title="Lock this recipient list and prepare its officer Approval Pay Code."
                                        class="@xl:flex-none inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                                        @click="
                                            createApprovalPayCode(worksheet)
                                        "
                                    >
                                        <LockKeyhole
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{
                                            authorizingWorksheet ===
                                            worksheet.reference
                                                ? 'Creating…'
                                                : batchActionLabel(worksheet)
                                        }}
                                    </button>
                                    <a
                                        v-else
                                        :href="show(worksheet.reference).url"
                                        class="@xl:flex-none inline-flex min-w-0 flex-1 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        {{ batchActionLabel(worksheet) }}
                                    </a>
                                    <button
                                        v-if="worksheet.status === 'draft'"
                                        type="button"
                                        :aria-label="`Delete draft ${worksheet.name}`"
                                        :disabled="deleteForm.processing"
                                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="deleteWorksheet(worksheet)"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="space-y-3">
                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center gap-2">
                            <FileSpreadsheet
                                class="size-4 text-slate-500 dark:text-slate-400"
                                aria-hidden="true"
                            />
                            <div>
                                <p
                                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                                >
                                    New Batch
                                </p>
                                <h2
                                    class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ activeCopy.importTitle }}
                                </h2>
                            </div>
                        </div>
                        <p
                            class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-300"
                        >
                            {{ activeCopy.importDescription }}
                        </p>
                        <div
                            data-testid="campaign-import-drop-zone"
                            role="group"
                            :tabindex="intakeForm.processing ? -1 : 0"
                            :aria-disabled="intakeForm.processing"
                            :aria-label="`Add a ${activeCopy.person} list from CSV, Excel, or pasted rows`"
                            class="mt-3 flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-5 py-5 text-center transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
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
                            <span
                                class="inline-flex size-10 items-center justify-center rounded-full bg-white text-sky-600 shadow-sm dark:bg-slate-900 dark:text-sky-300"
                            >
                                <Upload class="size-5" aria-hidden="true" />
                            </span>
                            <p
                                class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    intakeForm.processing
                                        ? `Reading ${activeCopy.people}…`
                                        : isDraggingIntake
                                          ? 'Drop To Inspect'
                                          : 'Choose A File Or Paste Rows'
                                }}
                            </p>
                            <button
                                v-if="!intakeForm.processing"
                                type="button"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                                @click.stop="openIntakeFilePicker"
                            >
                                <Upload class="size-3.5" aria-hidden="true" />
                                Choose File
                            </button>
                            <p
                                class="mt-3 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500"
                            >
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
                        <p
                            v-if="intakeFileError || intakeForm.errors.file"
                            class="mt-2 text-xs text-rose-600 dark:text-rose-300"
                        >
                            {{ intakeFileError || intakeForm.errors.file }}
                        </p>
                    </section>

                    <details
                        class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <summary class="cursor-pointer list-none p-4">
                            <div class="flex items-center gap-2">
                                <Plus
                                    class="size-4 text-slate-500 dark:text-slate-400"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p
                                        class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                                    >
                                        Start Empty
                                    </p>
                                    <h2
                                        class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ activeCopy.emptyBatchTitle }}
                                    </h2>
                                </div>
                            </div>
                        </summary>
                        <form
                            class="border-t border-slate-200 p-4 dark:border-slate-800"
                            @submit.prevent="createWorksheet"
                        >
                            <div class="flex items-center gap-2">
                                <Plus
                                    class="size-4 text-slate-500 dark:text-slate-400"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p
                                        class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                                    >
                                        New Batch
                                    </p>
                                    <h2
                                        class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            activeFlavor === 'payroll'
                                                ? 'Payroll Details'
                                                : 'Distribution Details'
                                        }}
                                    </h2>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <label
                                    class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        activeFlavor === 'payroll'
                                            ? 'Payroll Name'
                                            : 'Distribution Name'
                                    }}
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        maxlength="160"
                                        :placeholder="
                                            activeCopy.namePlaceholder
                                        "
                                        class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                    />
                                    <span
                                        v-if="form.errors.name"
                                        class="text-xs font-normal text-rose-600 dark:text-rose-300"
                                        >{{ form.errors.name }}</span
                                    >
                                </label>

                                <label
                                    class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    Payment Method
                                    <select
                                        v-model="form.fulfillment_mode"
                                        class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                    >
                                        <option value="pay_code_distribution">
                                            Pay Code distribution
                                        </option>
                                        <option value="direct_bank_transfer">
                                            Direct bank transfer
                                        </option>
                                    </select>
                                </label>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                            >
                                <Send class="size-4" aria-hidden="true" />
                                {{
                                    form.processing
                                        ? 'Creating…'
                                        : activeFlavor === 'payroll'
                                          ? 'Create Payroll Run'
                                          : 'Create Distribution Batch'
                                }}
                            </button>
                            <p
                                class="mt-2 text-center text-xs leading-4 text-slate-500 dark:text-slate-400"
                            >
                                Begins as a draft.
                            </p>
                        </form>
                    </details>
                </div>
            </section>
        </main>
        <CockpitCampaignIntakeDialog
            v-if="Object.keys(props.active_intake ?? {}).length > 0"
            :intake="props.active_intake as never"
        />
    </CockpitLayout>
</template>
