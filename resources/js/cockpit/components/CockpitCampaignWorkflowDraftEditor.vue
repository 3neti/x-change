<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatAbsoluteTime } from '../utils/dateTime';

type WorkflowPlan = {
    code: string;
    version: string;
    title: string;
    currency: string;
    premium_minor: number;
    benefit_minor: number | null;
    coverage: {
        basis: string;
        duration_days?: number | null;
        description?: string | null;
    } | null;
};

export type CampaignWorkflowDraftCatalog = {
    action_url: string;
    workflows: Array<{
        id: string;
        version: string;
        title: string;
        service: string;
        entry_methods: string[];
        plans: WorkflowPlan[];
        notifications: Array<{
            event: string;
            sms: string;
            placeholders: string[];
        }>;
        documents: Array<{
            type: string;
            title: string;
            allowed_mimes?: string[];
            max_size_mb?: number;
            multiple?: boolean;
        }>;
        checklist?: Array<{
            key: string;
            label: string;
            kind: string;
            required: boolean;
            review: string;
        }>;
        gates: string[];
        requires_review: boolean;
        configured: boolean;
    }>;
    drafts: Array<{
        reference: string;
        name: string;
        updated_at: string | null;
        workflow_title: string;
        plan_title: string | null;
        entry_method: string;
        publish_url?: string | null;
        expected_snapshot_hash?: string;
    }>;
};

const props = defineProps<{
    catalog: CampaignWorkflowDraftCatalog;
    templates: Array<{ id: number; name: string }>;
}>();
const dialog = ref<HTMLDialogElement>();
const publicationDialog = ref<HTMLDialogElement>();
const publicationDraft = ref<
    CampaignWorkflowDraftCatalog['drafts'][number] | null
>(null);
const publicationForm = useForm({ expected_snapshot_hash: '' });
const service = ref('');
const selection = ref('');
const planSelection = ref('');
const form = useForm({
    name: '',
    pay_code_template_id: '' as string | number,
    workflow_id: '',
    workflow_version: '',
    plan_code: null as string | null,
    plan_version: null as string | null,
    entry_method: '',
});
const services = computed(() => [
    ...new Set(props.catalog.workflows.map((workflow) => workflow.service)),
]);
const workflows = computed(() =>
    props.catalog.workflows.filter(
        (workflow) => workflow.service === service.value,
    ),
);
const workflow = computed(() =>
    workflows.value.find(
        (item) => `${item.id}@${item.version}` === selection.value,
    ),
);
const plan = computed(() =>
    workflow.value?.plans.find(
        (item) => `${item.code}@${item.version}` === planSelection.value,
    ),
);
const canSave = computed(() =>
    Boolean(
        form.name.trim() &&
        props.templates.some(
            (template) =>
                String(template.id) === String(form.pay_code_template_id),
        ) &&
        workflow.value &&
        (workflow.value.plans.length === 0 || plan.value) &&
        workflow.value.entry_methods.includes(form.entry_method) &&
        props.catalog.action_url &&
        !form.processing,
    ),
);

watch(service, () => {
    selection.value = '';
});
watch(workflow, (value) => {
    form.workflow_id = value?.id ?? '';
    form.workflow_version = value?.version ?? '';
    form.entry_method = '';
    planSelection.value = '';
    form.plan_code = null;
    form.plan_version = null;
});
watch(plan, (value) => {
    form.plan_code = value?.code ?? null;
    form.plan_version = value?.version ?? null;
});

function open(): void {
    form.clearErrors();
    dialog.value?.showModal();
}

function save(): void {
    if (!canSave.value) {
        return;
    }
    form.post(props.catalog.action_url, {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value?.close();
            form.reset();
            service.value = '';
        },
    });
}

function reviewPublication(
    draft: CampaignWorkflowDraftCatalog['drafts'][number],
): void {
    if (
        !draft.publish_url ||
        !draft.expected_snapshot_hash ||
        publicationForm.processing
    ) {
        return;
    }
    publicationForm.clearErrors();
    publicationDraft.value = draft;
    publicationForm.expected_snapshot_hash = draft.expected_snapshot_hash;
    publicationDialog.value?.showModal();
}

function publish(): void {
    if (
        !publicationDraft.value?.publish_url ||
        !publicationForm.expected_snapshot_hash ||
        publicationForm.processing
    ) {
        return;
    }
    publicationForm.post(publicationDraft.value.publish_url, {
        preserveScroll: true,
        onSuccess: () => {
            publicationDialog.value?.close();
            publicationDraft.value = null;
            publicationForm.reset();
        },
    });
}

function money(minor: number, currency: string): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(minor / 100);
}

function entryLabel(value: string): string {
    return value.replaceAll('_', ' ');
}

const fieldClass =
    'mt-1 min-h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50';
</script>

<template>
    <section
        class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
        data-testid="campaign-workflow-drafts"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-slate-950 dark:text-slate-50">
                    Workflow drafts
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Prepare a saved template. Drafts do not create a public link
                    or take payments.
                </p>
            </div>
            <button
                type="button"
                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold dark:border-slate-700"
                data-testid="campaign-workflow-draft-open"
                @click="open"
            >
                New workflow draft
            </button>
        </div>
        <ul
            v-if="catalog.drafts.length"
            class="mt-3 divide-y divide-slate-200 dark:divide-slate-800"
        >
            <li
                v-for="draft in catalog.drafts"
                :key="draft.reference"
                class="grid min-w-0 gap-1 py-3 text-sm"
            >
                <strong class="break-words">{{ draft.name }} · Draft</strong>
                <span class="break-words"
                    >{{ draft.workflow_title
                    }}<template v-if="draft.plan_title">
                        · {{ draft.plan_title }}</template
                    >
                    · {{ entryLabel(draft.entry_method) }}</span
                >
                <span class="text-xs text-slate-500"
                    >Saved {{ formatAbsoluteTime(draft.updated_at) }}</span
                >
                <button
                    v-if="draft.publish_url"
                    type="button"
                    :disabled="
                        publicationForm.processing ||
                        !draft.expected_snapshot_hash
                    "
                    class="min-h-10 justify-self-start rounded-lg border border-indigo-300 px-3 text-sm font-semibold disabled:opacity-50 dark:border-indigo-700"
                    data-testid="workflow-draft-publish-open"
                    @click="reviewPublication(draft)"
                >
                    Publish demo campaign
                </button>
            </li>
        </ul>
        <dialog
            ref="dialog"
            aria-labelledby="workflow-draft-title"
            aria-describedby="workflow-draft-description"
            class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 text-slate-950 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50"
            data-testid="campaign-workflow-draft-dialog"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 id="workflow-draft-title" class="text-lg font-semibold">
                    Create workflow draft
                </h2>
                <button
                    type="button"
                    aria-label="Close workflow draft"
                    class="min-h-10 px-3 text-sm"
                    @click="dialog?.close()"
                >
                    Close
                </button>
            </div>
            <p
                id="workflow-draft-description"
                class="mb-4 text-sm text-slate-500 dark:text-slate-400"
            >
                Save a new inactive template without changing the source
                template, existing campaigns, or Pay Codes. Publishing is a
                separate step.
            </p>
            <p
                v-if="!catalog.workflows.length"
                role="status"
                class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100"
            >
                No workflows are authorized for this account. Ask the host
                administrator to authorize an installed workflow.
            </p>
            <p v-if="!templates.length" class="mt-2 text-sm">
                Save a Pay Code template in Quick Generate first.
            </p>
            <form class="mt-4 grid min-w-0 gap-4" @submit.prevent="save">
                <label class="min-w-0 text-sm font-medium"
                    >Draft name<input
                        v-model="form.name"
                        :class="fieldClass"
                        maxlength="80"
                        required
                        data-testid="workflow-draft-name"
                /></label>
                <label class="min-w-0 text-sm font-medium"
                    >Source Pay Code template<select
                        v-model="form.pay_code_template_id"
                        :class="fieldClass"
                        required
                        data-testid="workflow-draft-template"
                    >
                        <option value="">Choose a saved template</option>
                        <option
                            v-for="template in templates"
                            :key="template.id"
                            :value="template.id"
                        >
                            {{ template.name }}
                        </option>
                    </select></label
                >
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    <label class="min-w-0 text-sm font-medium"
                        >Service<select
                            v-model="service"
                            :class="fieldClass"
                            required
                            data-testid="workflow-draft-service"
                        >
                            <option value="">Choose a service</option>
                            <option
                                v-for="item in services"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select></label
                    >
                    <label class="min-w-0 text-sm font-medium"
                        >Workflow<select
                            v-model="selection"
                            :class="fieldClass"
                            :disabled="!service"
                            required
                            data-testid="workflow-draft-workflow"
                        >
                            <option value="">Choose a workflow</option>
                            <option
                                v-for="item in workflows"
                                :key="`${item.id}@${item.version}`"
                                :value="`${item.id}@${item.version}`"
                            >
                                {{ item.title }} · {{ item.version }}
                            </option>
                        </select></label
                    >
                </div>
                <label
                    v-if="workflow?.plans.length"
                    class="min-w-0 text-sm font-medium"
                    >Plan<select
                        v-model="planSelection"
                        :class="fieldClass"
                        required
                        data-testid="workflow-draft-plan"
                    >
                        <option value="">Choose a plan</option>
                        <option
                            v-for="item in workflow.plans"
                            :key="`${item.code}@${item.version}`"
                            :value="`${item.code}@${item.version}`"
                        >
                            {{ item.title }} · {{ item.version }}
                        </option>
                    </select></label
                >
                <label v-if="workflow" class="min-w-0 text-sm font-medium"
                    >Entry method<select
                        v-model="form.entry_method"
                        :class="fieldClass"
                        required
                        data-testid="workflow-draft-entry"
                    >
                        <option value="">
                            Choose a supported entry method
                        </option>
                        <option
                            v-for="method in workflow.entry_methods"
                            :key="method"
                            :value="method"
                        >
                            {{ entryLabel(method) }}
                        </option>
                    </select></label
                >
                <section
                    v-if="workflow"
                    class="grid min-w-0 gap-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800"
                    data-testid="workflow-draft-preview"
                >
                    <h3 class="font-semibold">Driver defaults · Read only</h3>
                    <p
                        v-if="!workflow.configured"
                        class="text-amber-800 dark:text-amber-200"
                    >
                        Private connection is not ready. You can save a draft,
                        but it cannot be published yet.
                    </p>
                    <dl
                        v-if="plan"
                        class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1"
                    >
                        <dt>Premium</dt>
                        <dd>{{ money(plan.premium_minor, plan.currency) }}</dd>
                        <template v-if="plan.benefit_minor !== null"
                            ><dt>Benefit</dt>
                            <dd>
                                {{ money(plan.benefit_minor, plan.currency) }}
                            </dd></template
                        >
                        <template v-if="plan.coverage"
                            ><dt>Coverage</dt>
                            <dd class="break-words">
                                {{
                                    plan.coverage.description ??
                                    plan.coverage.basis
                                }}<template v-if="plan.coverage.duration_days">
                                    ·
                                    {{ plan.coverage.duration_days }}
                                    day(s)</template
                                >
                            </dd></template
                        >
                    </dl>
                    <p>
                        {{
                            workflow.requires_review
                                ? 'Reviewer approval required.'
                                : 'No reviewer approval declared by this workflow.'
                        }}
                    </p>
                    <ul
                        v-if="workflow.documents.length"
                        class="list-inside list-disc"
                    >
                        <li
                            v-for="document in workflow.documents"
                            :key="document.type"
                            class="break-words"
                        >
                            {{ document.title }} · Document<template
                                v-if="document.max_size_mb"
                            >
                                · Up to {{ document.max_size_mb }} MB</template
                            >
                        </li>
                    </ul>
                    <div
                        v-if="workflow.checklist?.length"
                        data-testid="workflow-draft-checklist"
                    >
                        <h4 class="font-semibold">Workflow requirements</h4>
                        <ul class="list-inside list-disc">
                            <li
                                v-for="item in workflow.checklist"
                                :key="item.key"
                                class="break-words"
                            >
                                {{ item.label }} ·
                                {{ item.required ? 'Required' : 'Optional' }} ·
                                {{ entryLabel(item.kind)
                                }}<template v-if="item.review !== 'none'">
                                    ·
                                    {{
                                        item.review === 'required'
                                            ? 'Review required'
                                            : 'Review optional'
                                    }}</template
                                >
                            </li>
                        </ul>
                    </div>
                    <p v-if="workflow.gates.length" class="break-words">
                        Workflow gates: {{ workflow.gates.join(', ') }}
                    </p>
                    <div
                        v-if="workflow.notifications.length"
                        class="grid min-w-0 gap-2"
                    >
                        <h4 class="font-semibold">SMS defaults</h4>
                        <p class="text-xs">
                            Placeholders are filled during the journey. Saving
                            this draft sends no SMS.
                        </p>
                        <div
                            v-for="notification in workflow.notifications"
                            :key="notification.event"
                        >
                            <p class="font-medium">
                                {{ entryLabel(notification.event) }}
                            </p>
                            <p class="whitespace-pre-wrap break-words">
                                {{ notification.sms }}
                            </p>
                        </div>
                    </div>
                    <p class="text-xs">
                        Claim inputs and stamp design remain in the source
                        template. No terms or messages can be overridden in this
                        draft editor.
                    </p>
                </section>
                <ul
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="grid gap-1 text-sm text-red-700 dark:text-red-300"
                >
                    <li v-for="(error, field) in form.errors" :key="field">
                        {{ error }}
                    </li>
                </ul>
                <button
                    type="submit"
                    :disabled="!canSave"
                    class="min-h-11 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    data-testid="workflow-draft-save"
                >
                    {{
                        form.processing
                            ? 'Saving draft…'
                            : 'Save draft template'
                    }}
                </button>
            </form>
        </dialog>
        <dialog
            ref="publicationDialog"
            aria-labelledby="workflow-publication-title"
            aria-describedby="workflow-publication-description"
            class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 text-slate-950 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50"
            data-testid="workflow-draft-publication-dialog"
            @cancel="publicationForm.processing && $event.preventDefault()"
        >
            <h2 id="workflow-publication-title" class="text-lg font-semibold">
                Publish demo campaign
            </h2>
            <p class="mt-2 break-words font-medium">
                {{ publicationDraft?.name }}
            </p>
            <p
                id="workflow-publication-description"
                class="mt-2 text-sm text-slate-600 dark:text-slate-300"
            >
                This freezes the saved workflow template and creates its demo
                campaign. It does not create a QR Ph, take a payment, or send
                SMS. Payment and claim processing remain subject to the host's
                existing runtime gates. Existing campaigns and Pay Codes do not
                change.
            </p>
            <form
                class="mt-4 grid gap-3"
                data-testid="workflow-draft-publication-form"
                @submit.prevent="publish"
            >
                <ul
                    v-if="Object.keys(publicationForm.errors).length"
                    role="alert"
                    class="grid gap-1 text-sm text-red-700 dark:text-red-300"
                >
                    <li
                        v-for="(error, field) in publicationForm.errors"
                        :key="field"
                    >
                        {{ error }}
                    </li>
                </ul>
                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        :disabled="publicationForm.processing"
                        class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm dark:border-slate-700"
                        @click="publicationDialog?.close()"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="
                            publicationForm.processing ||
                            !publicationDraft?.publish_url ||
                            !publicationForm.expected_snapshot_hash
                        "
                        class="min-h-11 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-50"
                        data-testid="workflow-draft-publish-confirm"
                    >
                        {{
                            publicationForm.processing
                                ? 'Publishing…'
                                : 'Publish demo campaign'
                        }}
                    </button>
                </div>
            </form>
        </dialog>
    </section>
</template>
