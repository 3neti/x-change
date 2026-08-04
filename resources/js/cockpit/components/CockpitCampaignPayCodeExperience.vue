<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Eye,
    Link2,
    MessageSquareText,
    Palette,
    Save,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import voucherBlueprint from '@/routes/x-change/cockpit/campaigns/voucher-blueprint';
import CockpitPayCodeCanvas from './CockpitPayCodeCanvas.vue';
import type {
    CockpitInstructionCapabilityReadiness,
    CockpitInstructionCapabilityReadinessMap,
} from '../types';

type Blueprint = {
    onboarding?: boolean;
    inputs?: { fields?: string[] };
    feedback?: { channels?: string[] };
    rider?: {
        message?: string | null;
        url?: string | null;
        redirect_timeout?: number | null;
        splash?: string | null;
        splash_timeout?: number | null;
        message_format?: string | null;
        splash_format?: string | null;
        stamp?: Record<string, unknown>;
    };
    validation?: Record<string, { required?: boolean; on_failure?: string }>;
    claim?: { onboarding?: { mode?: string } };
    expiry_days?: number;
};

const props = withDefaults(
    defineProps<{
        worksheetReference: string;
        worksheetName: string;
        fulfillmentMode: string;
        status: string;
        currency: string;
        beneficiaryCount: number;
        representativeAmountMinor?: number;
        representativeRecipient?: string;
        blueprint: Blueprint;
        revision: number;
        blueprintHash?: string | null;
        onboardingOtpRequired?: boolean;
        instructionCapabilities?: CockpitInstructionCapabilityReadinessMap;
    }>(),
    {
        onboardingOtpRequired: true,
        instructionCapabilities: () => ({}),
    },
);

type EditorKey = 'instructions' | 'stamp' | 'claim';
const activeEditor = ref<EditorKey>('instructions');
const editorTabs = [
    ['instructions', 'Instructions', MessageSquareText],
    ['stamp', 'Stamp', Palette],
    ['claim', 'Claim', ShieldCheck],
] as const;
const inputOptions = [
    ['mobile', 'Mobile'],
    ['name', 'Name'],
    ['email', 'Email'],
    ['otp', 'OTP'],
    ['reference_code', 'Reference'],
    ['kyc', 'Identity'],
    ['selfie', 'Selfie'],
    ['signature', 'Signature'],
    ['location', 'Location'],
] as const;
const validationOptions = [
    ['otp', 'OTP'],
    ['selfie', 'Selfie'],
    ['signature', 'Signature'],
] as const;

const initialValidation = (key: string) => ({
    required: props.blueprint.validation?.[key]?.required === true,
    on_failure:
        props.blueprint.validation?.[key]?.on_failure === 'warn'
            ? 'warn'
            : 'block',
});

const form = useForm({
    expected_revision: props.revision,
    blueprint: {
        onboarding:
            props.blueprint.onboarding === true ||
            props.blueprint.claim?.onboarding?.mode === 'required',
        inputs: { fields: [...(props.blueprint.inputs?.fields ?? [])] },
        feedback: {
            channels: [...(props.blueprint.feedback?.channels ?? [])],
        },
        rider: {
            message: props.blueprint.rider?.message ?? props.worksheetName,
            url: props.blueprint.rider?.url ?? '',
            redirect_timeout: props.blueprint.rider?.redirect_timeout ?? 0,
            splash: props.blueprint.rider?.splash ?? '',
            splash_timeout: props.blueprint.rider?.splash_timeout ?? 3,
            message_format: props.blueprint.rider?.message_format ?? 'plain',
            splash_format: props.blueprint.rider?.splash_format ?? 'html',
            stamp: {
                source: String(
                    props.blueprint.rider?.stamp?.source ?? 'automatic',
                ),
                artwork_source: String(
                    props.blueprint.rider?.stamp?.artwork_source ?? 'x_change',
                ),
                fit: String(props.blueprint.rider?.stamp?.fit ?? 'cover'),
                position: String(
                    props.blueprint.rider?.stamp?.position ?? 'center',
                ),
                theme: String(
                    props.blueprint.rider?.stamp?.theme ?? 'automatic',
                ),
                scrim: Number(props.blueprint.rider?.stamp?.scrim ?? 18),
                show_logo: props.blueprint.rider?.stamp?.show_logo !== false,
                show_tagline:
                    props.blueprint.rider?.stamp?.show_tagline !== false,
                claim_marker: String(
                    props.blueprint.rider?.stamp?.claim_marker ?? 'qr',
                ),
                claim_marker_position: String(
                    props.blueprint.rider?.stamp?.claim_marker_position ??
                        'bottom_right',
                ),
                version: Number(props.blueprint.rider?.stamp?.version ?? 2),
            },
        },
        validation: {
            otp: initialValidation('otp'),
            selfie: initialValidation('selfie'),
            signature: initialValidation('signature'),
        },
        expiry_days: props.blueprint.expiry_days ?? 7,
    },
});

const isDraft = computed(() => props.status === 'draft');
const selectedInputLabels = computed(() =>
    inputOptions
        .filter(([value]) => form.blueprint.inputs.fields.includes(value))
        .map(([, label]) => label),
);
const requiredValidationLabels = computed(() =>
    validationOptions
        .filter(
            ([value]) =>
                form.blueprint.validation[
                    value as keyof typeof form.blueprint.validation
                ].required,
        )
        .map(([, label]) => label),
);
const onboardingOtpEnforced = computed(
    () =>
        form.blueprint.onboarding &&
        (props.onboardingOtpRequired !== false ||
            /^(\+|09|639|63)/.test(props.representativeRecipient ?? '')),
);

const capabilityReadiness = (
    key: string,
): CockpitInstructionCapabilityReadiness | null =>
    props.instructionCapabilities[key] ?? null;
const capabilityUnavailable = (key: string): boolean =>
    capabilityReadiness(key)?.issuance_allowed === false;
const capabilityForFeedbackChannel = (channel: string): string =>
    channel === 'mobile' ? 'feedback.sms' : `feedback.${channel}`;
const selectedUnavailableCapabilities = computed<
    CockpitInstructionCapabilityReadiness[]
>(() => {
    const selected = new Set<string>();

    form.blueprint.inputs.fields.forEach((field) => {
        if (['location', 'kyc', 'otp', 'selfie', 'signature'].includes(field)) {
            selected.add(field);
        }
    });
    validationOptions.forEach(([key]) => {
        if (form.blueprint.validation[key].required) {
            selected.add(key);
        }
    });
    form.blueprint.feedback.channels.forEach((channel) => {
        selected.add(capabilityForFeedbackChannel(channel));
    });

    return [...selected]
        .map((key) => capabilityReadiness(key))
        .filter(
            (capability): capability is CockpitInstructionCapabilityReadiness =>
                capability?.issuance_allowed === false,
        );
});

function applyOnboardingDependencies(): void {
    if (!form.blueprint.onboarding) {
        return;
    }

    const enforceOtp =
        props.onboardingOtpRequired !== false ||
        /^(\+|09|639|63)/.test(props.representativeRecipient ?? '');
    const fields = new Set(form.blueprint.inputs.fields);
    fields.add('name');
    fields.add('email');
    fields.add('mobile');

    if (enforceOtp) {
        fields.add('otp');
        form.blueprint.validation.otp.required = true;
        form.blueprint.validation.otp.on_failure = 'block';
    }

    form.blueprint.inputs.fields = inputOptions
        .map(([value]) => value)
        .filter((field) => fields.has(field));
}

const onboardingLockedFields = computed<string[]>(() => {
    if (!form.blueprint.onboarding) {
        return [];
    }

    return [
        'mobile',
        'name',
        'email',
        ...(onboardingOtpEnforced.value ? ['otp'] : []),
    ];
});

watch(
    [() => form.blueprint.onboarding, onboardingOtpEnforced],
    applyOnboardingDependencies,
    { immediate: true, flush: 'sync' },
);
const instructionKeys = computed(() => [
    ...form.blueprint.inputs.fields,
    ...requiredValidationLabels.value.map((label) => label.toLowerCase()),
]);
const previewSource = computed<'default' | 'message' | 'url' | 'splash'>(() => {
    const artwork = String(form.blueprint.rider.stamp.artwork_source);

    if (artwork === 'url' && form.blueprint.rider.url) {
        return 'url';
    }

    if (artwork === 'splash' && form.blueprint.rider.splash) {
        return 'splash';
    }

    if (artwork === 'none') {
        return 'message';
    }

    return 'default';
});
const previewDocument = computed(() =>
    previewSource.value === 'splash' ? form.blueprint.rider.splash : '',
);
const scopeLabel = computed(() =>
    props.fulfillmentMode === 'direct_bank_transfer'
        ? 'Fallback Pay Code'
        : 'Pay Code',
);
const shortHash = computed(() =>
    props.blueprintHash ? props.blueprintHash.slice(0, 10) : 'Legacy Snapshot',
);

function save(): void {
    if (selectedUnavailableCapabilities.value.length > 0) {
        return;
    }

    form.put(voucherBlueprint.update(props.worksheetReference).url, {
        preserveScroll: true,
    });
}

function selectEditor(key: EditorKey): void {
    activeEditor.value = key;
}
</script>

<template>
    <section
        data-testid="campaign-pay-code-experience"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
        <header
            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p
                        class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                    >
                        {{ scopeLabel }}
                    </p>
                    <span
                        class="rounded-full bg-orange-50 px-2 py-0.5 text-[0.65rem] font-semibold text-orange-700 dark:bg-orange-950/50 dark:text-orange-200"
                    >
                        All {{ beneficiaryCount }} Recipients
                    </span>
                </div>
                <h2
                    class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50"
                >
                    Recipient Experience
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ isDraft ? 'Editable Draft' : 'Locked · ' + shortHash }}
                </span>
                <button
                    v-if="isDraft"
                    type="button"
                    :disabled="
                        form.processing ||
                        selectedUnavailableCapabilities.length > 0
                    "
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950"
                    @click="save"
                >
                    <Save class="size-4" />
                    {{ form.processing ? 'Saving…' : 'Save Changes' }}
                </button>
            </div>
        </header>

        <div
            class="grid gap-0 xl:grid-cols-[minmax(0,0.95fr)_minmax(24rem,1.05fr)]"
        >
            <div
                class="border-b border-slate-200 p-4 xl:border-b-0 xl:border-r dark:border-slate-800"
            >
                <nav
                    class="mb-4 grid grid-cols-3 rounded-xl bg-slate-100 p-1 dark:bg-slate-950"
                    aria-label="Campaign Pay Code editor"
                >
                    <button
                        v-for="[key, label, icon] in editorTabs"
                        :key="key"
                        type="button"
                        :class="
                            activeEditor === key
                                ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-800 dark:text-white'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold"
                        @click="selectEditor(key)"
                    >
                        <component :is="icon" class="size-3.5" />
                        {{ label }}
                    </button>
                </nav>

                <fieldset
                    :disabled="!isDraft"
                    class="grid gap-4 disabled:opacity-70"
                >
                    <template v-if="activeEditor === 'instructions'">
                        <label
                            class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Purpose
                            <input
                                v-model="form.blueprint.rider.message"
                                maxlength="5000"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </label>
                        <label
                            class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Rider Link
                            <span class="relative">
                                <Link2
                                    class="pointer-events-none absolute left-3 top-2.5 size-4 text-slate-400"
                                />
                                <input
                                    v-model="form.blueprint.rider.url"
                                    type="url"
                                    placeholder="https://"
                                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </span>
                        </label>
                        <label
                            class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                        >
                            Rider Splash
                            <textarea
                                v-model="form.blueprint.rider.splash"
                                rows="5"
                                placeholder="Optional HTML, Markdown, or plain text introduction"
                                class="resize-y rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-mono text-xs text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </label>
                    </template>

                    <template v-else-if="activeEditor === 'stamp'">
                        <div>
                            <p
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Artwork
                            </p>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                <label
                                    v-for="[value, label] in [
                                        ['x_change', 'x-change'],
                                        ['url', 'Link'],
                                        ['splash', 'Splash'],
                                        ['none', 'None'],
                                    ]"
                                    :key="value"
                                    :class="
                                        form.blueprint.rider.stamp
                                            .artwork_source === value
                                            ? 'border-orange-400 bg-orange-50 text-orange-800 dark:border-orange-700 dark:bg-orange-950/40 dark:text-orange-200'
                                            : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'
                                    "
                                    class="cursor-pointer rounded-xl border px-2 py-2 text-center text-xs font-semibold"
                                >
                                    <input
                                        v-model="
                                            form.blueprint.rider.stamp
                                                .artwork_source
                                        "
                                        type="radio"
                                        :value="value"
                                        class="sr-only"
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                Fit
                                <select
                                    v-model="form.blueprint.rider.stamp.fit"
                                    class="rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                </select>
                            </label>
                            <label
                                class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                Theme
                                <select
                                    v-model="form.blueprint.rider.stamp.theme"
                                    class="rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="automatic">Automatic</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </label>
                            <label
                                class="col-span-2 grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                Contrast ·
                                {{ form.blueprint.rider.stamp.scrim }}%
                                <input
                                    v-model="form.blueprint.rider.stamp.scrim"
                                    type="range"
                                    min="0"
                                    max="100"
                                    class="accent-orange-500"
                                />
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <label
                                class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                ><input
                                    v-model="
                                        form.blueprint.rider.stamp.show_logo
                                    "
                                    type="checkbox"
                                    class="rounded border-slate-300 text-orange-600"
                                />
                                x-change Logo</label
                            >
                            <label
                                class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                ><input
                                    v-model="
                                        form.blueprint.rider.stamp.show_tagline
                                    "
                                    type="checkbox"
                                    class="rounded border-slate-300 text-orange-600"
                                />
                                Tagline</label
                            >
                        </div>
                    </template>

                    <template v-else>
                        <div>
                            <p
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Recipient Provides
                            </p>
                            <div
                                class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3"
                            >
                                <label
                                    v-for="[value, label] in inputOptions"
                                    :key="value"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                    :class="{
                                        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100':
                                            capabilityUnavailable(value),
                                    }"
                                >
                                    <input
                                        v-model="form.blueprint.inputs.fields"
                                        type="checkbox"
                                        :value="value"
                                        class="rounded border-slate-300 text-orange-600"
                                        :disabled="
                                            onboardingLockedFields.includes(
                                                value,
                                            ) ||
                                            (capabilityUnavailable(value) &&
                                                !form.blueprint.inputs.fields.includes(
                                                    value,
                                                ))
                                        "
                                        :data-onboarding-locked="
                                            onboardingLockedFields.includes(
                                                value,
                                            )
                                                ? 'true'
                                                : undefined
                                        "
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Safeguards
                            </p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="[value, label] in validationOptions"
                                    :key="value"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                    :class="{
                                        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100':
                                            capabilityUnavailable(value),
                                    }"
                                >
                                    <input
                                        v-model="
                                            form.blueprint.validation[value]
                                                .required
                                        "
                                        type="checkbox"
                                        class="rounded border-slate-300 text-orange-600"
                                        :disabled="
                                            (value === 'otp' &&
                                                onboardingOtpEnforced) ||
                                            (capabilityUnavailable(value) &&
                                                !form.blueprint.validation[
                                                    value
                                                ].required)
                                        "
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-100"
                                data-testid="campaign-onboarding-toggle"
                            >
                                <span class="grid gap-0.5">
                                    <span class="font-semibold">
                                        Set Up Recipient Accounts
                                    </span>
                                    <span
                                        class="font-normal text-emerald-700 dark:text-emerald-300"
                                    >
                                        Apply onboarding to every beneficiary
                                        Pay Code.
                                    </span>
                                </span>
                                <input
                                    v-model="form.blueprint.onboarding"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-emerald-300 text-emerald-600"
                                    @change="applyOnboardingDependencies"
                                />
                            </label>
                            <label
                                class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                Valid For
                                <select
                                    v-model="form.blueprint.expiry_days"
                                    class="rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option :value="1">1 Day</option>
                                    <option :value="3">3 Days</option>
                                    <option :value="7">7 Days</option>
                                    <option :value="14">14 Days</option>
                                    <option :value="30">30 Days</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Status Feedback
                            </p>
                            <div class="mt-2 flex gap-2">
                                <label
                                    v-for="[value, label] in [
                                        ['mobile', 'SMS'],
                                        ['email', 'Email'],
                                    ]"
                                    :key="value"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                    :class="{
                                        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100':
                                            capabilityUnavailable(
                                                capabilityForFeedbackChannel(
                                                    value,
                                                ),
                                            ),
                                    }"
                                >
                                    <input
                                        v-model="
                                            form.blueprint.feedback.channels
                                        "
                                        type="checkbox"
                                        :value="value"
                                        class="rounded border-slate-300 text-orange-600"
                                        :disabled="
                                            capabilityUnavailable(
                                                capabilityForFeedbackChannel(
                                                    value,
                                                ),
                                            ) &&
                                            !form.blueprint.feedback.channels.includes(
                                                value,
                                            )
                                        "
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </div>
                        <div
                            v-if="selectedUnavailableCapabilities.length > 0"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
                            data-testid="campaign-instruction-capability-warning"
                        >
                            <p class="font-semibold">
                                Remove unavailable services before saving or
                                requesting approval
                            </p>
                            <ul class="mt-2 space-y-1">
                                <li
                                    v-for="capability in selectedUnavailableCapabilities"
                                    :key="capability.key"
                                >
                                    {{ capability.label }} —
                                    {{ capability.reason }}
                                </li>
                            </ul>
                        </div>
                    </template>
                </fieldset>

                <p
                    v-if="form.errors.blueprint"
                    class="mt-3 text-xs text-rose-600 dark:text-rose-300"
                >
                    {{ form.errors.blueprint }}
                </p>
            </div>

            <div class="bg-slate-50/70 p-4 dark:bg-slate-950/40">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Eye class="size-4 text-slate-500" />
                        <div>
                            <p
                                class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                            >
                                Preview
                            </p>
                            <p
                                class="text-[0.7rem] text-slate-500 dark:text-slate-400"
                            >
                                Each recipient receives this experience with
                                their own details.
                            </p>
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-[0.65rem] font-semibold text-slate-500 shadow-sm dark:bg-slate-900 dark:text-slate-400"
                    >
                        <Sparkles class="size-3" />
                        {{
                            selectedInputLabels.length +
                            requiredValidationLabels.length
                        }}
                        Instructions
                    </span>
                </div>

                <CockpitPayCodeCanvas
                    :amount="(representativeAmountMinor ?? 0) / 100"
                    :currency="currency"
                    :recipient="representativeRecipient"
                    :purpose="form.blueprint.rider.message"
                    claim-outcome="provider_disbursement"
                    voucher-type="redeemable"
                    :expiry="`${form.blueprint.expiry_days} days`"
                    :instruction-keys="instructionKeys"
                    :has-rider-design="previewSource !== 'default'"
                    :rider-design-source="previewSource"
                    :rider-design-document="previewDocument"
                    presentation="live"
                />

                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div
                        class="rounded-xl bg-white px-2 py-2 shadow-sm dark:bg-slate-900"
                    >
                        <BadgeCheck class="mx-auto size-3.5 text-emerald-500" />
                        <p
                            class="mt-1 text-[0.65rem] font-semibold text-slate-600 dark:text-slate-300"
                        >
                            {{ beneficiaryCount }} Recipients
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-white px-2 py-2 shadow-sm dark:bg-slate-900"
                    >
                        <ShieldCheck class="mx-auto size-3.5 text-blue-500" />
                        <p
                            class="mt-1 text-[0.65rem] font-semibold text-slate-600 dark:text-slate-300"
                        >
                            {{ requiredValidationLabels.length }} Safeguards
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-white px-2 py-2 shadow-sm dark:bg-slate-900"
                    >
                        <Palette class="mx-auto size-3.5 text-orange-500" />
                        <p
                            class="mt-1 text-[0.65rem] font-semibold text-slate-600 dark:text-slate-300"
                        >
                            Shared Stamp
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
