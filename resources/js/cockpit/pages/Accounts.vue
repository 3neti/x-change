<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update as updateFundingDestination } from '@/routes/x-change/cockpit/accounts/providers/funding-destination';
import { store as storeNetbankTokenRotation } from '@/routes/x-change/cockpit/accounts/providers/netbank/token-rotation';
import { computed, ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitAccountProvider,
    CockpitAccountsPageProps,
} from '../types';

const props = defineProps<CockpitAccountsPageProps>();

const netbank = computed(() => provider('netbank'));
const paynamics = computed(() => provider('paynamics_constellation'));
const netbankMode = ref(netbank.value.mode);
const netbankEnrollment = ref('generate');
const paynamicsMode = ref(paynamics.value.mode);

const netbankForm = useForm({
    mode: netbank.value.mode,
    enrollment: 'generate',
    account_number: '',
    account_name: '',
    vca_alias: '',
    vca_alias_token: '',
});
const paynamicsForm = useForm({
    mode: paynamics.value.mode,
    wallet_id: '',
});
const rotationForm = useForm({
    confirm_rotation: false,
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
    netbankForm.enrollment = netbankEnrollment.value;
    netbankForm.patch(updateFundingDestination('netbank'), {
        preserveScroll: true,
        onSuccess: () =>
            netbankForm.reset(
                'account_number',
                'account_name',
                'vca_alias',
                'vca_alias_token',
            ),
    });
}

function savePaynamics(): void {
    paynamicsForm.mode = paynamicsMode.value;
    paynamicsForm.patch(updateFundingDestination('paynamics'), {
        preserveScroll: true,
        onSuccess: () => paynamicsForm.reset('wallet_id'),
    });
}

function rotateNetbankToken(): void {
    rotationForm.post(storeNetbankTokenRotation(), {
        preserveScroll: true,
        onSuccess: () => rotationForm.reset(),
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
                            <div>
                                <label
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    for="netbank-enrollment"
                                    >Token enrollment</label
                                >
                                <select
                                    id="netbank-enrollment"
                                    v-model="netbankEnrollment"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="generate">
                                        Generate with NetBank
                                    </option>
                                    <option value="import">
                                        Import existing token
                                    </option>
                                </select>
                                <p
                                    v-if="netbankForm.errors.enrollment"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ netbankForm.errors.enrollment }}
                                </p>
                            </div>
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
                                <label
                                    v-if="netbankEnrollment === 'import'"
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    data-testid="netbank-token-field"
                                >
                                    VCA alias token
                                    <input
                                        v-model="netbankForm.vca_alias_token"
                                        type="password"
                                        autocomplete="new-password"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    />
                                    <span
                                        class="mt-1 block font-normal text-slate-500"
                                        >Write-only; it will not be shown
                                        again.</span
                                    >
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

                    <details
                        v-if="netbank.dedicated.can_rotate_token"
                        class="border-t border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-900 dark:bg-amber-950/20"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-amber-900 dark:text-amber-200"
                        >
                            Rotate dedicated VCA token
                        </summary>
                        <form
                            class="mt-3 space-y-3"
                            @submit.prevent="rotateNetbankToken"
                        >
                            <p
                                class="text-xs leading-5 text-amber-800 dark:text-amber-300"
                            >
                                Rotation invalidates the stored token and
                                requests a replacement from NetBank.
                            </p>
                            <label
                                class="flex items-start gap-2 text-xs text-amber-950 dark:text-amber-100"
                            >
                                <input
                                    v-model="rotationForm.confirm_rotation"
                                    type="checkbox"
                                    class="mt-0.5"
                                />
                                I understand this changes the active funding
                                credential.
                            </label>
                            <button
                                type="submit"
                                :disabled="rotationForm.processing"
                                class="rounded-lg border border-amber-500 px-3 py-2 text-xs font-semibold text-amber-950 disabled:opacity-50 dark:text-amber-100"
                            >
                                Rotate token
                            </button>
                        </form>
                    </details>
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
                                                >Shared wallet</span
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
                                                >Dedicated wallet</span
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
                                Wallet ID
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
                                A reachable wallet is not proof of ownership.
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
