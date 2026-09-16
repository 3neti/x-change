<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    ClipboardList,
    Copy,
    FileSpreadsheet,
    Globe2,
    HandCoins,
    Link2,
    LockKeyhole,
    Plus,
    QrCode,
    Send,
    Share2,
    Trash2,
    Upload,
    Users,
    X,
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

type CampaignUsageProfile = {
    key: string;
    label: string;
    description: string;
    entry_point: string;
    person_type: string;
    pay_code_generation: string;
    default_capabilities: string[];
};

type CampaignEndpointCapability = {
    key: string;
    label: string;
};

type CampaignPayCodeTemplate = {
    id: number;
    reference: string;
    name: string;
    description: string | null;
    amount_minor: number;
    currency: string;
    flow_type: string;
    input_fields: string[];
};

type EndpointCampaign = {
    reference: string;
    title: string;
    description: string | null;
    status: string;
    merchant_display_name: string;
    merchant_slug: string;
    endpoint_slug: string;
    public_url: string;
    qr_data_uri: string | null;
    usage_count: number;
    starts_limit: number | null;
    last_started_at: string | null;
    expires_at: string | null;
    usage_key: string;
    usage_label: string;
    capabilities: string[];
    availability: Record<string, unknown>;
    limits: Record<string, unknown>;
    template: {
        id: number;
        reference: string;
        name: string;
        amount_minor: number;
        currency: string;
    } | null;
};

type CampaignsPageProps = CockpitHeaderPageProps & {
    worksheets: CampaignWorksheet[];
    active_intake?: Record<string, unknown>;
    campaign_usage_profiles?: CampaignUsageProfile[];
    endpoint_capabilities?: CampaignEndpointCapability[];
    pay_code_templates?: CampaignPayCodeTemplate[];
    endpoint_campaigns?: EndpointCampaign[];
    endpoint_campaign_form?: {
        action_url: string;
        default_timezone: string;
    };
};

const props = defineProps<CampaignsPageProps>();

type CampaignFlavor = CampaignWorksheet['profile'] | 'endpoints';

const campaignFlavors: CampaignFlavor[] = ['payroll', 'assistance', 'endpoints'];
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
    endpoints: {
        eyebrow: 'Endpoints',
        title: 'Endpoint Campaigns',
        description:
            'Turn an existing Pay Code template into a public QR or link that generates a fresh Pay Code when opened.',
        listTitle: 'Public Endpoints',
        people: 'Starts',
        person: 'participant',
        total: 'Exposure',
        emptyTitle: 'No endpoint campaigns yet',
        emptyDescription:
            'Create a public campaign link from a Pay Code template.',
        importTitle: 'Create Endpoint Campaign',
        importDescription:
            'Select a reusable Pay Code template, choose usage, and publish a governed QR/link endpoint.',
        emptyBatchTitle: 'Create Endpoint Campaign',
        namePlaceholder: 'Insurance application',
    },
};
const activeCopy = computed(() => flavorCopy[activeFlavor.value]);
const visibleWorksheets = computed(() =>
    activeFlavor.value === 'endpoints'
        ? []
        : props.worksheets.filter(
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
const usageProfiles = computed<CampaignUsageProfile[]>(
    () => props.campaign_usage_profiles ?? [],
);
const endpointCapabilities = computed<CampaignEndpointCapability[]>(
    () => props.endpoint_capabilities ?? [],
);
const payCodeTemplates = computed<CampaignPayCodeTemplate[]>(
    () => props.pay_code_templates ?? [],
);
const endpointCampaigns = computed<EndpointCampaign[]>(
    () => props.endpoint_campaigns ?? [],
);
const endpointForm = useForm({
    title: '',
    description: '',
    usage_key: usageProfiles.value[0]?.key ?? 'lead',
    capabilities: usageProfiles.value[0]?.default_capabilities ?? [],
    pay_code_template_id: payCodeTemplates.value[0]?.id ?? null,
    endpoint_slug: '',
    starts_limit: null as number | null,
    budget_cap_minor: null as number | null,
    starts_at: '',
    expires_at: '',
    daily_window_start: '',
    daily_window_end: '',
    timezone:
        props.endpoint_campaign_form?.default_timezone ??
        Intl.DateTimeFormat().resolvedOptions().timeZone ??
        'UTC',
});
const deleteForm = useForm({});
const authorizationForm = useForm({});
const authorizingWorksheet = ref<string | null>(null);
const intakeForm = useForm<{ file: File | null }>({ file: null });
const intakeFileInput = ref<HTMLInputElement | null>(null);
const intakeDragDepth = ref(0);
const isDraggingIntake = ref(false);
const intakeFileError = ref<string | null>(null);
const selectedEndpointStamp = ref<EndpointCampaign | null>(null);

watch(activeFlavor, (profile) => {
    if (profile !== 'endpoints') {
        form.profile = profile;
    }
});

watch(
    () => endpointForm.usage_key,
    (usageKey) => {
        const profile = usageProfiles.value.find(
            (candidate) => candidate.key === usageKey,
        );

        endpointForm.capabilities = profile?.default_capabilities ?? [];
    },
);

watch(payCodeTemplates, (templates) => {
    if (endpointForm.pay_code_template_id === null && templates.length > 0) {
        endpointForm.pay_code_template_id = templates[0].id;
    }
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

function createEndpointCampaign(): void {
    if (!props.endpoint_campaign_form?.action_url) {
        return;
    }

    endpointForm.post(props.endpoint_campaign_form.action_url, {
        preserveScroll: true,
        onSuccess: () => {
            endpointForm.reset(
                'title',
                'description',
                'endpoint_slug',
                'starts_limit',
                'budget_cap_minor',
                'starts_at',
                'expires_at',
                'daily_window_start',
                'daily_window_end',
            );
        },
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

function selectedTemplate(): CampaignPayCodeTemplate | undefined {
    return payCodeTemplates.value.find(
        (template) => template.id === endpointForm.pay_code_template_id,
    );
}

function selectedUsageProfile(): CampaignUsageProfile | undefined {
    return usageProfiles.value.find(
        (profile) => profile.key === endpointForm.usage_key,
    );
}

function templateSummary(template: CampaignPayCodeTemplate | null): string {
    if (template === null) {
        return 'Template unavailable';
    }

    const inputs =
        template.input_fields.length > 0
            ? `${template.input_fields.length} field${template.input_fields.length === 1 ? '' : 's'}`
            : 'standard claim';

    return `${display(template.flow_type)} · ${peso(template.amount_minor)} · ${inputs}`;
}

function campaignExposure(campaign: EndpointCampaign): string {
    const amount = campaign.template?.amount_minor ?? 0;
    const maxStarts = campaign.starts_limit ?? null;

    if (amount <= 0 || maxStarts === null) {
        return amount > 0 ? `${peso(amount)} each` : 'No principal';
    }

    return `${peso(amount * maxStarts)} cap`;
}

function endpointAvailabilitySummary(campaign: EndpointCampaign): string {
    const parts = [
        campaign.starts_limit
            ? `${campaign.usage_count} of ${campaign.starts_limit} starts used`
            : `${campaign.usage_count} starts`,
        campaign.expires_at
            ? `Expires ${updatedRelativeTime(campaign.expires_at)}`
            : 'Open until paused',
    ];

    return parts.join(' · ');
}

function openEndpointStamp(campaign: EndpointCampaign): void {
    selectedEndpointStamp.value = campaign;
}

function closeEndpointStamp(): void {
    selectedEndpointStamp.value = null;
}

async function copyEndpointUrl(url: string): Promise<void> {
    const clipboard = globalThis.navigator?.clipboard;

    if (!clipboard?.writeText) {
        return;
    }

    await clipboard.writeText(url);
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
        mobile-presentation="edge"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto max-w-7xl space-y-5 px-4 md:px-0"
            data-testid="cockpit-campaigns-page"
        >
            <section
                class="-mx-4 border-y border-slate-200 bg-white p-4 shadow-none md:mx-0 md:rounded-2xl md:border md:shadow-sm dark:border-slate-800 dark:bg-slate-900"
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
                    class="mt-4 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-950"
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
                            v-else-if="flavor === 'assistance'"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <QrCode v-else class="size-4" aria-hidden="true" />
                        {{
                            flavor === 'payroll'
                                ? 'Payroll'
                                : flavor === 'assistance'
                                  ? 'Ayuda'
                                  : 'Endpoints'
                        }}
                    </button>
                </div>
            </section>

            <section
                v-if="activeFlavor !== 'endpoints'"
                class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]"
            >
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
                            :aria-label="`Add ${activeCopy.person} list from CSV, Excel, or pasted rows`"
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

            <section
                v-else
                class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]"
                data-testid="campaign-endpoint-canvas"
            >
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                Public Campaign Links
                            </p>
                            <h2
                                class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Endpoint Campaigns
                            </h2>
                        </div>
                        <span
                            class="w-fit rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >{{ endpointCampaigns.length }} live cards</span
                        >
                    </div>

                    <div
                        v-if="endpointCampaigns.length === 0"
                        class="flex min-h-72 flex-col items-center justify-center px-6 text-center"
                    >
                        <QrCode
                            class="size-9 text-slate-300 dark:text-slate-700"
                            aria-hidden="true"
                        />
                        <p
                            class="mt-3 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            No endpoint campaigns yet
                        </p>
                        <p
                            class="mt-1 max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400"
                        >
                            Create one from a saved Pay Code template. The
                            public link mints a fresh Pay Code when opened.
                        </p>
                    </div>

                    <div
                        v-else
                        class="grid gap-4 p-4 lg:grid-cols-2"
                        data-testid="campaign-endpoint-list"
                    >
                        <article
                            v-for="campaign in endpointCampaigns"
                            :key="campaign.reference"
                            class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
                            :data-testid="`campaign-endpoint-card-${campaign.reference}`"
                        >
                            <div class="flex items-start gap-3">
                                <img
                                    v-if="campaign.qr_data_uri"
                                    :src="campaign.qr_data_uri"
                                    :alt="`Endpoint stamp code for ${campaign.title}`"
                                    class="size-24 rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800"
                                    data-testid="campaign-endpoint-qr"
                                />
                                <div
                                    v-else
                                    class="flex size-24 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <QrCode
                                        class="size-8"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="rounded-full bg-white px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            >{{ campaign.usage_label }}</span
                                        >
                                        <span
                                            :class="
                                                statusClasses(campaign.status)
                                            "
                                            class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold"
                                            >{{
                                                display(campaign.status)
                                            }}</span
                                        >
                                    </div>
                                    <h3
                                        class="mt-2 truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ campaign.title }}
                                    </h3>
                                    <p
                                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            campaign.description ||
                                            campaign.template?.name ||
                                            'Pay Code endpoint'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-2 rounded-xl bg-white p-3 text-xs dark:bg-slate-900"
                            >
                                <div class="flex items-center gap-2">
                                    <Globe2
                                        class="size-3.5 shrink-0 text-slate-400"
                                        aria-hidden="true"
                                    />
                                    <a
                                        :href="campaign.public_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="min-w-0 truncate text-emerald-700 underline decoration-emerald-300 underline-offset-4 dark:text-emerald-300"
                                        >{{ campaign.public_url }}</a
                                    >
                                    <button
                                        type="button"
                                        class="ml-auto inline-flex size-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                        :aria-label="`Copy ${campaign.title} endpoint URL`"
                                        @click="
                                            copyEndpointUrl(
                                                campaign.public_url,
                                            )
                                        "
                                    >
                                        <Copy
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                                <dl class="grid grid-cols-3 gap-2">
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            Starts
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ campaign.usage_count }}
                                            <span
                                                v-if="campaign.starts_limit"
                                                class="font-normal text-slate-400"
                                                >/ {{
                                                    campaign.starts_limit
                                                }}</span
                                            >
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            Exposure
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ campaignExposure(campaign) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            Expires
                                        </dt>
                                        <dd
                                            class="font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{
                                                campaign.expires_at
                                                    ? updatedRelativeTime(
                                                          campaign.expires_at,
                                                      )
                                                    : 'Open'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-950"
                                :aria-label="`Share ${campaign.title} endpoint stamp`"
                                :data-testid="`campaign-endpoint-share-stamp-${campaign.reference}`"
                                @click="openEndpointStamp(campaign)"
                            >
                                <Share2 class="size-4" aria-hidden="true" />
                                Share Stamp
                            </button>
                        </article>
                    </div>
                </div>

                <form
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="campaign-endpoint-create-form"
                    @submit.prevent="createEndpointCampaign"
                >
                    <div class="flex items-center gap-2">
                        <Link2
                            class="size-4 text-slate-500 dark:text-slate-400"
                            aria-hidden="true"
                        />
                        <div>
                            <p
                                class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                New Endpoint
                            </p>
                            <h2
                                class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Create from Template
                            </h2>
                        </div>
                    </div>
                    <p
                        class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-300"
                    >
                        The selected Pay Code template remains the source of
                        truth. This campaign only adds a public entry point,
                        limits, and availability.
                    </p>

                    <div
                        v-if="payCodeTemplates.length === 0"
                        class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        Save a Pay Code template in Quick Generate before
                        creating an endpoint campaign.
                    </div>

                    <div v-else class="mt-4 grid gap-3">
                        <label
                            class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Campaign name
                            <input
                                v-model="endpointForm.title"
                                type="text"
                                maxlength="120"
                                placeholder="Insurance application"
                                class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                            />
                            <span
                                v-if="endpointForm.errors.title"
                                class="text-xs font-normal text-rose-600 dark:text-rose-300"
                                >{{ endpointForm.errors.title }}</span
                            >
                        </label>

                        <label
                            class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Pay Code template
                            <select
                                v-model="endpointForm.pay_code_template_id"
                                class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                            >
                                <option
                                    v-for="template in payCodeTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                            <span
                                class="text-xs font-normal text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    selectedTemplate()
                                        ? templateSummary(
                                              selectedTemplate() ?? null,
                                          )
                                        : 'Choose a template'
                                }}
                            </span>
                            <span
                                v-if="endpointForm.errors.pay_code_template_id"
                                class="text-xs font-normal text-rose-600 dark:text-rose-300"
                                >{{
                                    endpointForm.errors.pay_code_template_id
                                }}</span
                            >
                        </label>

                        <label
                            class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Campaign usage
                            <select
                                v-model="endpointForm.usage_key"
                                class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                            >
                                <option
                                    v-for="profile in usageProfiles"
                                    :key="profile.key"
                                    :value="profile.key"
                                >
                                    {{ profile.label }}
                                </option>
                            </select>
                            <span
                                class="text-xs font-normal text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    selectedUsageProfile()?.description ||
                                    'Operator-defined endpoint campaign.'
                                }}
                            </span>
                        </label>

                        <label
                            class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Endpoint slug
                            <input
                                v-model="endpointForm.endpoint_slug"
                                type="text"
                                maxlength="120"
                                placeholder="insurance-application"
                                class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                            />
                            <span
                                class="text-xs font-normal text-slate-500 dark:text-slate-400"
                            >
                                Leave blank to derive it from the campaign name.
                            </span>
                            <span
                                v-if="endpointForm.errors.endpoint_slug"
                                class="text-xs font-normal text-rose-600 dark:text-rose-300"
                                >{{ endpointForm.errors.endpoint_slug }}</span
                            >
                        </label>

                        <fieldset
                            class="grid gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                        >
                            <legend
                                class="px-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400"
                            >
                                Capabilities
                            </legend>
                            <label
                                v-for="capability in endpointCapabilities"
                                :key="capability.key"
                                class="flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-200"
                            >
                                <input
                                    v-model="endpointForm.capabilities"
                                    type="checkbox"
                                    :value="capability.key"
                                    class="rounded border-slate-300 text-slate-950 focus:ring-slate-950 dark:border-slate-700 dark:bg-slate-950"
                                />
                                {{ capability.label }}
                            </label>
                        </fieldset>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Max starts
                                <input
                                    v-model.number="endpointForm.starts_limit"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="100"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Budget cap
                                <input
                                    v-model.number="
                                        endpointForm.budget_cap_minor
                                    "
                                    type="number"
                                    min="0"
                                    step="100"
                                    placeholder="10000"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Start
                                <input
                                    v-model="endpointForm.starts_at"
                                    type="datetime-local"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                End
                                <input
                                    v-model="endpointForm.expires_at"
                                    type="datetime-local"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Daily start
                                <input
                                    v-model="endpointForm.daily_window_start"
                                    type="time"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                            <label
                                class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Daily end
                                <input
                                    v-model="endpointForm.daily_window_end"
                                    type="time"
                                    class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10"
                                />
                            </label>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="
                            endpointForm.processing ||
                            payCodeTemplates.length === 0
                        "
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                    >
                        <QrCode class="size-4" aria-hidden="true" />
                        {{
                            endpointForm.processing
                                ? 'Creating…'
                                : 'Create Endpoint Campaign'
                        }}
                    </button>
                </form>
            </section>
        </main>
        <CockpitCampaignIntakeDialog
            v-if="Object.keys(props.active_intake ?? {}).length > 0"
            :intake="props.active_intake as never"
        />

        <div
            v-if="selectedEndpointStamp"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 p-3 backdrop-blur-sm sm:items-center"
            role="presentation"
            data-testid="campaign-endpoint-stamp-overlay"
            @click.self="closeEndpointStamp"
        >
            <section
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`campaign-endpoint-stamp-title-${selectedEndpointStamp.reference}`"
                class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-4 shadow-2xl outline-none dark:bg-slate-950"
                :data-testid="`campaign-endpoint-stamp-modal-${selectedEndpointStamp.reference}`"
                tabindex="-1"
                @keydown.esc="closeEndpointStamp"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                        >
                            Endpoint Campaign Stamp
                        </p>
                        <h2
                            :id="`campaign-endpoint-stamp-title-${selectedEndpointStamp.reference}`"
                            class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ selectedEndpointStamp.title }}
                        </h2>
                        <p
                            class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400"
                        >
                            Ready to print, post, send, or advertise.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
                        aria-label="Close endpoint stamp"
                        data-testid="campaign-endpoint-stamp-close"
                        @click="closeEndpointStamp"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div
                    class="mt-4 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-950 text-white shadow-inner dark:border-slate-800"
                    data-testid="campaign-endpoint-stamp-preview"
                >
                    <div
                        class="grid gap-4 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.35),transparent_32%),linear-gradient(135deg,#020617,#111827_52%,#064e3b)] p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-emerald-200"
                                >
                                    {{ selectedEndpointStamp.usage_label }}
                                    campaign
                                </p>
                                <h3
                                    class="mt-2 text-2xl font-semibold leading-tight"
                                >
                                    {{ selectedEndpointStamp.title }}
                                </h3>
                                <p
                                    class="mt-2 text-sm leading-5 text-slate-200"
                                >
                                    {{
                                        selectedEndpointStamp.description ||
                                        selectedEndpointStamp.template?.name ||
                                        'Scan to start this Pay Code experience.'
                                    }}
                                </p>
                            </div>
                            <span
                                :class="
                                    statusClasses(selectedEndpointStamp.status)
                                "
                                class="shrink-0 rounded-full px-2 py-1 text-[0.65rem] font-semibold"
                                >{{
                                    display(selectedEndpointStamp.status)
                                }}</span
                            >
                        </div>

                        <div
                            class="grid gap-4 rounded-2xl bg-white/95 p-4 text-slate-950 shadow-xl sm:grid-cols-[10rem_1fr]"
                        >
                            <div
                                class="flex min-h-40 items-center justify-center rounded-xl border border-slate-200 bg-white p-2"
                            >
                                <img
                                    v-if="selectedEndpointStamp.qr_data_uri"
                                    :src="selectedEndpointStamp.qr_data_uri"
                                    :alt="`Endpoint stamp code for ${selectedEndpointStamp.title}`"
                                    class="size-36"
                                    data-testid="campaign-endpoint-stamp-qr"
                                />
                                <QrCode
                                    v-else
                                    class="size-16 text-slate-300"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="grid content-between gap-4">
                                <div>
                                    <p
                                        class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500"
                                    >
                                        Presented by
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{
                                            selectedEndpointStamp.merchant_display_name
                                        }}
                                    </p>
                                </div>
                                <dl class="grid gap-2 text-xs">
                                    <div>
                                        <dt
                                            class="font-semibold uppercase tracking-[0.14em] text-slate-500"
                                        >
                                            Availability
                                        </dt>
                                        <dd class="mt-0.5 font-medium">
                                            {{
                                                endpointAvailabilitySummary(
                                                    selectedEndpointStamp,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="font-semibold uppercase tracking-[0.14em] text-slate-500"
                                        >
                                            Exposure
                                        </dt>
                                        <dd class="mt-0.5 font-medium">
                                            {{
                                                campaignExposure(
                                                    selectedEndpointStamp,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/10 p-3 text-xs text-slate-100"
                        >
                            <p
                                class="font-semibold uppercase tracking-[0.18em] text-emerald-200"
                            >
                                Public endpoint
                            </p>
                            <p
                                class="mt-1 break-all font-medium"
                                data-testid="campaign-endpoint-stamp-url"
                            >
                                {{ selectedEndpointStamp.public_url }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
                        data-testid="campaign-endpoint-stamp-copy"
                        @click="
                            copyEndpointUrl(selectedEndpointStamp.public_url)
                        "
                    >
                        <Copy class="size-4" aria-hidden="true" />
                        Copy link
                    </button>
                    <a
                        :href="selectedEndpointStamp.public_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white"
                        data-testid="campaign-endpoint-stamp-open"
                    >
                        <Globe2 class="size-4" aria-hidden="true" />
                        Open endpoint
                    </a>
                </div>
            </section>
        </div>
    </CockpitLayout>
</template>
