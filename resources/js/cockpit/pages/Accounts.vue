<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update as updateFundingQrMerchantProfile } from '@/routes/x-change/cockpit/accounts/funding-qr-merchant-profile';
import { store as runFundingDestinationScenario } from '@/routes/x-change/cockpit/accounts/scenarios/funding-destinations';
import { update as updateFundingDestination } from '@/routes/x-change/cockpit/accounts/providers/funding-destination';
import { computed, ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitAccountProvider,
    CockpitAccountScenarioResult,
    CockpitAccountsPageProps,
} from '../types';

const props = defineProps<CockpitAccountsPageProps>();

const netbank = computed(() => provider('netbank'));
const paynamics = computed(() => provider('paynamics_constellation'));
const netbankMode = ref(netbank.value.mode);
const paynamicsMode = ref(paynamics.value.mode);
const scenarioRunning = ref(false);
const scenarioError = ref<string | null>(null);
const scenarioResult = ref<CockpitAccountScenarioResult | null>(null);
const activeScenarioStepIndex = ref(0);
const activeScenarioStep = computed(
    () => scenarioResult.value?.steps[activeScenarioStepIndex.value] ?? null,
);

const netbankForm = useForm({
    mode: netbank.value.mode,
    account_number: '',
    account_name: '',
    vca_alias: '',
});
const paynamicsForm = useForm({
    mode: paynamics.value.mode,
    wallet_id: '',
});
const merchantProfileForm = useForm({
    name: props.funding_qr_merchant_profile.name,
    city: props.funding_qr_merchant_profile.city,
    merchant_category_code:
        props.funding_qr_merchant_profile.merchant_category_code,
    merchant_name_template:
        props.funding_qr_merchant_profile.merchant_name_template,
});

function provider(
    code: CockpitAccountProvider['code'],
): CockpitAccountProvider {
    const match = props.account_read_model.providers.find(
        (candidate) => candidate.code === code,
    );

    if (match) {
        return match;
    }

    return {
        code,
        label: code === 'netbank' ? 'NetBank' : 'Paynamics',
        mode: 'shared',
        shared: { status: 'not_configured' },
        dedicated: {
            configured: false,
            status: 'not_configured',
            verification_status: 'not_configured',
            can_activate: false,
            can_rotate_token: false,
            ownership_verification_required: code === 'paynamics_constellation',
        },
    };
}

function saveNetbank(): void {
    netbankForm.mode = netbankMode.value;
    netbankForm.patch(updateFundingDestination('netbank'), {
        preserveScroll: true,
        onSuccess: () =>
            netbankForm.reset('account_number', 'account_name', 'vca_alias'),
    });
}

function savePaynamics(): void {
    paynamicsForm.mode = paynamicsMode.value;
    paynamicsForm.patch(updateFundingDestination('paynamics'), {
        preserveScroll: true,
        onSuccess: () => paynamicsForm.reset('wallet_id'),
    });
}

function saveFundingQrMerchantProfile(): void {
    merchantProfileForm.patch(updateFundingQrMerchantProfile(), {
        preserveScroll: true,
    });
}

function displayLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayTime(value?: string | null): string {
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

async function runAccountScenario(): Promise<void> {
    if (scenarioRunning.value || props.account_scenario?.enabled !== true) {
        return;
    }

    scenarioRunning.value = true;
    scenarioError.value = null;
    const route = runFundingDestinationScenario();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = await safeJson(response);

        if (!response.ok) {
            scenarioError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'The rollback walkthrough could not be completed.';

            return;
        }

        if (
            body.schema !==
                'x-change.lifecycle.account-management-scenario.v1' ||
            !Array.isArray(body.steps)
        ) {
            scenarioError.value =
                'The rollback walkthrough returned an unexpected response.';

            return;
        }

        scenarioResult.value = body as CockpitAccountScenarioResult;
        activeScenarioStepIndex.value = 0;
    } catch {
        scenarioError.value =
            'The rollback walkthrough could not reach the Cockpit service.';
    } finally {
        scenarioRunning.value = false;
    }
}

function previousScenarioStep(): void {
    activeScenarioStepIndex.value = Math.max(
        0,
        activeScenarioStepIndex.value - 1,
    );
}

function nextScenarioStep(): void {
    activeScenarioStepIndex.value = Math.min(
        (scenarioResult.value?.steps.length ?? 1) - 1,
        activeScenarioStepIndex.value + 1,
    );
}

function restartScenario(): void {
    activeScenarioStepIndex.value = 0;
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const body = await response.json();

        return typeof body === 'object' && body !== null
            ? (body as Record<string, unknown>)
            : {};
    } catch {
        return {};
    }
}
</script>

<template>
    <CockpitLayout
        active-navigation="accounts"
        :cockpit-header-read-model="cockpit_header_read_model"
    >
        <div
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-accounts-page"
        >
            <div
                v-if="funding_account_notice"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                role="status"
            >
                {{ funding_account_notice }}
            </div>

            <section
                class="grid gap-4 overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 px-5 py-5 text-white shadow-sm lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-6"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-sky-300 uppercase"
                        >
                            Funding controls
                        </p>
                        <span
                            class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[0.65rem] font-semibold tracking-wide text-slate-200 uppercase"
                        >
                            PIN protected
                        </span>
                    </div>
                    <h1
                        class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Accounts
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                        Choose where authoritative provider settlement is
                        addressed. This never adds funds manually; the Account
                        ledger changes only after verified provider settlement.
                    </p>
                </div>
                <dl
                    class="grid min-w-72 grid-cols-2 gap-x-5 gap-y-2 rounded-xl border border-white/10 bg-white/5 p-4 text-xs"
                >
                    <div>
                        <dt class="text-slate-400">Account</dt>
                        <dd class="mt-1 font-semibold text-white">
                            {{ account_read_model.account.reference }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Currency</dt>
                        <dd class="mt-1 font-semibold text-white">
                            {{ account_read_model.account.currency }}
                        </dd>
                    </div>
                    <div class="col-span-2 border-t border-white/10 pt-2">
                        <dt class="text-slate-400">Credit authority</dt>
                        <dd class="mt-1 text-slate-200">
                            Verified provider settlement only
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-950 dark:bg-slate-950"
                data-testid="funding-qr-merchant-profile"
            >
                <div
                    class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300"
                            >
                                QR presentation
                            </p>
                            <span
                                class="rounded-full bg-emerald-100 px-2 py-1 text-[0.65rem] font-semibold text-emerald-800 uppercase dark:bg-emerald-950 dark:text-emerald-200"
                            >
                                Presentation only
                            </span>
                        </div>
                        <h2
                            class="mt-1.5 text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Funding QR merchant profile
                        </h2>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            These fields label the reusable QR Ph image. They
                            never select the Account, recognize a payment, or
                            authorize settlement; the immutable funding address
                            does that.
                        </p>
                        <div
                            class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                        >
                            Saving a change retires only the previous encrypted
                            QR fixture. Opening Funding generates the
                            replacement once and then keeps it ready for reuse.
                        </div>
                    </div>

                    <form
                        class="grid gap-4 sm:grid-cols-2"
                        @submit.prevent="saveFundingQrMerchantProfile"
                    >
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Merchant name
                            <input
                                v-model="merchantProfileForm.name"
                                maxlength="25"
                                autocomplete="organization"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                data-testid="funding-qr-merchant-name"
                            />
                            <span
                                v-if="merchantProfileForm.errors.name"
                                class="mt-1 block font-normal text-rose-600"
                                >{{ merchantProfileForm.errors.name }}</span
                            >
                        </label>
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            City
                            <input
                                v-model="merchantProfileForm.city"
                                maxlength="15"
                                autocomplete="address-level2"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                            />
                            <span
                                v-if="merchantProfileForm.errors.city"
                                class="mt-1 block font-normal text-rose-600"
                                >{{ merchantProfileForm.errors.city }}</span
                            >
                        </label>
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Category
                            <select
                                v-model="
                                    merchantProfileForm.merchant_category_code
                                "
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                            >
                                <option
                                    v-for="option in funding_qr_merchant_profile.category_options"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.code }} · {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            QR label
                            <select
                                v-model="
                                    merchantProfileForm.merchant_name_template
                                "
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                            >
                                <option value="{name}">Merchant name</option>
                                <option value="{name} - {city}">
                                    Merchant name · City
                                </option>
                                <option value="{app_name} - {name}">
                                    X-Change · Merchant name
                                </option>
                            </select>
                            <span
                                v-if="
                                    merchantProfileForm.errors
                                        .merchant_name_template
                                "
                                class="mt-1 block font-normal text-rose-600"
                                >{{
                                    merchantProfileForm.errors
                                        .merchant_name_template
                                }}</span
                            >
                        </label>
                        <div class="sm:col-span-2 sm:flex sm:justify-end">
                            <button
                                type="submit"
                                :disabled="merchantProfileForm.processing"
                                class="h-10 w-full rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50 sm:w-auto"
                                data-testid="save-funding-qr-merchant-profile"
                            >
                                {{
                                    merchantProfileForm.processing
                                        ? 'Saving…'
                                        : 'Save QR presentation'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm dark:border-sky-900 dark:bg-slate-950"
                data-testid="account-lifecycle-scenario"
            >
                <div
                    class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Lifecycle walkthrough
                            </p>
                            <span
                                class="rounded-full bg-sky-50 px-2 py-1 text-[0.65rem] font-semibold text-sky-700 uppercase dark:bg-sky-950/50 dark:text-sky-200"
                            >
                                Rollback only
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                            >
                                0 provider calls
                            </span>
                        </div>
                        <h2
                            class="mt-1.5 text-lg font-semibold text-slate-950 dark:text-white"
                        >
                            Appreciate the funding destination lifecycle
                        </h2>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            Walk through shared defaults, dedicated NetBank
                            routing, immutable token-free intent snapshots, and
                            Paynamics ownership controls. Nothing is funded or
                            retained.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="
                            scenarioRunning ||
                            account_scenario?.enabled !== true
                        "
                        class="h-10 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        data-testid="run-account-scenario"
                        @click="runAccountScenario"
                    >
                        {{
                            scenarioRunning
                                ? 'Running safely…'
                                : account_scenario?.enabled === true
                                  ? 'Run safe walkthrough'
                                  : 'Unavailable in this environment'
                        }}
                    </button>
                </div>

                <div
                    v-if="scenarioError"
                    class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-200"
                    role="alert"
                >
                    {{ scenarioError }}
                </div>

                <div
                    v-if="scenarioResult && activeScenarioStep"
                    class="border-t border-sky-100 bg-slate-50/70 p-4 sm:p-5 dark:border-sky-950 dark:bg-slate-900/50"
                    data-testid="account-scenario-stepper"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div
                            class="flex flex-wrap items-center gap-1.5"
                            aria-label="Scenario steps"
                        >
                            <button
                                v-for="(step, index) in scenarioResult.steps"
                                :key="step.key"
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition"
                                :class="
                                    index === activeScenarioStepIndex
                                        ? 'bg-sky-600 text-white'
                                        : 'bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700'
                                "
                                :aria-label="`Open step ${index + 1}: ${step.label}`"
                                @click="activeScenarioStepIndex = index"
                            >
                                {{ index + 1 }}
                            </button>
                        </div>
                        <p class="text-xs font-medium text-slate-500">
                            Step {{ activeScenarioStepIndex + 1 }} of
                            {{ scenarioResult.steps.length }}
                        </p>
                    </div>

                    <article
                        class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h3
                                    class="text-base font-semibold text-slate-950 dark:text-white"
                                >
                                    {{ activeScenarioStep.label }}
                                </h3>
                                <p
                                    class="mt-1 max-w-4xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                                >
                                    {{ activeScenarioStep.summary }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    activeScenarioStep.outcome === 'blocked'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                "
                            >
                                {{ displayLabel(activeScenarioStep.outcome) }}
                            </span>
                        </div>

                        <div
                            class="mt-4 grid gap-3"
                            :class="
                                activeScenarioStep.providers.length > 1
                                    ? 'md:grid-cols-2'
                                    : ''
                            "
                        >
                            <div
                                v-for="providerState in activeScenarioStep.providers"
                                :key="providerState.code"
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ providerState.label }}
                                    </p>
                                    <span
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        {{ displayLabel(providerState.mode) }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    {{
                                        providerState.mode === 'dedicated'
                                            ? providerState.dedicated
                                                  .display_reference
                                            : providerState.shared
                                                  .display_reference
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{
                                        providerState.mode === 'dedicated'
                                            ? displayLabel(
                                                  providerState.dedicated
                                                      .verification_status,
                                              )
                                            : displayLabel(
                                                  providerState.shared.status,
                                              )
                                    }}
                                </p>
                            </div>
                        </div>

                        <dl
                            class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="fact in activeScenarioStep.facts"
                                :key="fact.label"
                                class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt class="text-[0.68rem] text-slate-500">
                                    {{ fact.label }}
                                </dt>
                                <dd
                                    class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ fact.value }}
                                </dd>
                            </div>
                        </dl>
                    </article>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p
                            class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Rollback confirmed · balance unchanged · nothing
                            persisted
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                :disabled="activeScenarioStepIndex === 0"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                data-testid="previous-scenario-step"
                                @click="previousScenarioStep"
                            >
                                Previous
                            </button>
                            <button
                                v-if="
                                    activeScenarioStepIndex <
                                    scenarioResult.steps.length - 1
                                "
                                type="button"
                                class="h-9 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-sky-500 dark:text-slate-950"
                                data-testid="next-scenario-step"
                                @click="nextScenarioStep"
                            >
                                Next
                            </button>
                            <button
                                v-else
                                type="button"
                                class="h-9 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-sky-500 dark:text-slate-950"
                                data-testid="restart-account-scenario"
                                @click="restartScenario"
                            >
                                Restart
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-2">
                <section
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="netbank-account-card"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Bank destination
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                NetBank
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            {{ displayLabel(netbank.mode) }}
                        </span>
                    </div>

                    <form class="space-y-5 p-5" @submit.prevent="saveNetbank">
                        <fieldset>
                            <legend
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                Funding destination
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label
                                    class="cursor-pointer rounded-xl border p-3"
                                    :class="
                                        netbankMode === 'shared'
                                            ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/30'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                >
                                    <span class="flex items-start gap-2">
                                        <input
                                            v-model="netbankMode"
                                            type="radio"
                                            value="shared"
                                            class="mt-0.5"
                                        />
                                        <span>
                                            <span
                                                class="block text-sm font-semibold text-slate-900 dark:text-white"
                                                >Shared treasury</span
                                            >
                                            <span
                                                class="mt-1 block text-xs leading-5 text-slate-600 dark:text-slate-400"
                                                >Platform-managed default ·
                                                {{
                                                    netbank.shared
                                                        .display_reference ??
                                                    'not configured'
                                                }}</span
                                            >
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="cursor-pointer rounded-xl border p-3"
                                    :class="
                                        netbankMode === 'dedicated'
                                            ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/30'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                >
                                    <span class="flex items-start gap-2">
                                        <input
                                            v-model="netbankMode"
                                            type="radio"
                                            value="dedicated"
                                            class="mt-0.5"
                                            data-testid="netbank-dedicated-mode"
                                        />
                                        <span>
                                            <span
                                                class="block text-sm font-semibold text-slate-900 dark:text-white"
                                                >Dedicated account</span
                                            >
                                            <span
                                                class="mt-1 block text-xs leading-5 text-slate-600 dark:text-slate-400"
                                                >Your corporate account and VCA
                                                alias</span
                                            >
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <div
                            v-if="netbankMode === 'dedicated'"
                            class="space-y-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900"
                            data-testid="netbank-dedicated-fields"
                        >
                            <p
                                class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs leading-5 text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200"
                                data-testid="netbank-registration-token-policy"
                            >
                                NetBank registration tokens are generated
                                automatically for each new VCA registration.
                                They are never stored as Account credentials,
                                and generating one does not revoke an earlier
                                token.
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Corporate account number
                                    <input
                                        v-model="netbankForm.account_number"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    />
                                    <span
                                        v-if="netbankForm.errors.account_number"
                                        class="mt-1 block text-xs text-rose-600"
                                        >{{
                                            netbankForm.errors.account_number
                                        }}</span
                                    >
                                </label>
                                <label
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Account name
                                    <input
                                        v-model="netbankForm.account_name"
                                        autocomplete="off"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    />
                                </label>
                                <label
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    5-digit VCA alias
                                    <input
                                        v-model="netbankForm.vca_alias"
                                        inputmode="numeric"
                                        maxlength="5"
                                        autocomplete="off"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    />
                                </label>
                            </div>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-slate-500">
                                Saving requires a recent security PIN
                                confirmation.
                            </p>
                            <button
                                type="submit"
                                :disabled="netbankForm.processing"
                                class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-sky-500 dark:text-slate-950"
                            >
                                {{
                                    netbankForm.processing
                                        ? 'Saving…'
                                        : 'Save NetBank destination'
                                }}
                            </button>
                        </div>
                    </form>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="paynamics-account-card"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-violet-700 uppercase dark:text-violet-300"
                            >
                                EMI destination
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                Paynamics
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            {{ displayLabel(paynamics.mode) }}
                        </span>
                    </div>

                    <form class="space-y-5 p-5" @submit.prevent="savePaynamics">
                        <fieldset>
                            <legend
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                Funding destination
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label
                                    class="cursor-pointer rounded-xl border p-3"
                                    :class="
                                        paynamicsMode === 'shared'
                                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                >
                                    <span class="flex items-start gap-2">
                                        <input
                                            v-model="paynamicsMode"
                                            type="radio"
                                            value="shared"
                                            class="mt-0.5"
                                        />
                                        <span>
                                            <span
                                                class="block text-sm font-semibold text-slate-900 dark:text-white"
                                                >Shared provider account</span
                                            >
                                            <span
                                                class="mt-1 block text-xs leading-5 text-slate-600 dark:text-slate-400"
                                                >Platform-managed default ·
                                                {{
                                                    paynamics.shared
                                                        .display_reference ??
                                                    'not configured'
                                                }}</span
                                            >
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="cursor-pointer rounded-xl border p-3"
                                    :class="
                                        paynamicsMode === 'dedicated'
                                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                >
                                    <span class="flex items-start gap-2">
                                        <input
                                            v-model="paynamicsMode"
                                            type="radio"
                                            value="dedicated"
                                            class="mt-0.5"
                                            data-testid="paynamics-dedicated-mode"
                                        />
                                        <span>
                                            <span
                                                class="block text-sm font-semibold text-slate-900 dark:text-white"
                                                >Dedicated provider account</span
                                            >
                                            <span
                                                class="mt-1 block text-xs leading-5 text-slate-600 dark:text-slate-400"
                                                >Requires authoritative
                                                ownership proof</span
                                            >
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <div
                            v-if="paynamicsMode === 'dedicated'"
                            class="space-y-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900"
                            data-testid="paynamics-dedicated-fields"
                        >
                            <label
                                class="block text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Provider account ID
                                <input
                                    v-model="paynamicsForm.wallet_id"
                                    autocomplete="off"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-950"
                                />
                            </label>
                            <div
                                class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                                data-testid="paynamics-ownership-warning"
                            >
                                A reachable provider account is not proof of ownership.
                                The candidate may be recorded, but dedicated
                                funding remains blocked until Paynamics supplies
                                authoritative ownership verification.
                            </div>
                        </div>

                        <dl
                            v-if="paynamics.dedicated.configured"
                            class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-800"
                        >
                            <div>
                                <dt class="text-slate-500">Reference</dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ paynamics.dedicated.display_reference }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Verification</dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        displayLabel(
                                            paynamics.dedicated
                                                .verification_status,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-slate-500">
                                No reachability check can activate dedicated
                                funding.
                            </p>
                            <button
                                type="submit"
                                :disabled="paynamicsForm.processing"
                                class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-violet-400 dark:text-slate-950"
                            >
                                {{
                                    paynamicsForm.processing
                                        ? 'Saving…'
                                        : 'Save Paynamics destination'
                                }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <details
                class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                data-testid="connection-history"
            >
                <summary
                    class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white"
                >
                    Connection history ·
                    {{ account_read_model.connection_history.length }}
                </summary>
                <div
                    v-if="account_read_model.connection_history.length"
                    class="mt-4 overflow-x-auto"
                >
                    <table class="w-full min-w-160 text-left text-xs">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="pb-2 font-medium">Provider</th>
                                <th class="pb-2 font-medium">Reference</th>
                                <th class="pb-2 font-medium">Status</th>
                                <th class="pb-2 font-medium">Verification</th>
                                <th class="pb-2 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="item in account_read_model.connection_history"
                                :key="item.id"
                            >
                                <td class="py-2">
                                    {{ displayLabel(item.provider) }}
                                </td>
                                <td class="py-2">
                                    {{ item.display_reference ?? '—' }}
                                </td>
                                <td class="py-2">
                                    {{ displayLabel(item.status) }}
                                </td>
                                <td class="py-2">
                                    {{ displayLabel(item.verification_status) }}
                                </td>
                                <td class="py-2">
                                    {{ displayTime(item.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="mt-3 text-xs text-slate-500">
                    No dedicated provider connections have been recorded.
                </p>
            </details>
        </div>
    </CockpitLayout>
</template>
