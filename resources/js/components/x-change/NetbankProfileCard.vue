<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as accountsIndex } from '@/routes/x-change/cockpit/accounts';

type SourceAccountReadiness = {
    enabled?: boolean;
    ready?: boolean;
    checked?: boolean;
    account_number_masked?: string | null;
    available_balance_minor?: number | null;
    currency?: string | null;
    message?: string | null;
};

type NetbankProfile = {
    active?: boolean;
    client_alias?: string | null;
    source_account_number?: string | null;
    sender_customer_id?: string | null;
    source_account_readiness?: SourceAccountReadiness | null;
};

defineProps<{
    profile: NetbankProfile;
}>();

const formatMinorMoney = (value?: number | null, currency = 'PHP') => {
    if (value === null || value === undefined) {
        return 'Not checked';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(value / 100);
};
</script>

<template>
    <section
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
    >
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.22em] text-slate-500 uppercase"
                >
                    Provider ledger
                </p>
                <h2
                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-white"
                >
                    NetBank source account
                </h2>
                <p
                    class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400"
                >
                    NetBank uses the local x-change ledger for user
                    spendability, then checks the configured source account when
                    readiness checks are enabled.
                </p>
            </div>

            <span
                class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold"
                :class="
                    profile.active
                        ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300'
                        : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                "
            >
                {{ profile.active ? 'Active provider' : 'Configured' }}
            </span>
        </div>

        <div
            class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3 dark:bg-slate-900"
        >
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Client alias
                </p>
                <p
                    class="mt-1 font-mono text-sm text-slate-950 dark:text-white"
                >
                    {{ profile.client_alias || 'Not configured' }}
                </p>
            </div>

            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Source account
                </p>
                <p
                    class="mt-1 font-mono text-sm text-slate-950 dark:text-white"
                >
                    {{
                        profile.source_account_readiness
                            ?.account_number_masked ||
                        profile.source_account_number ||
                        'Not configured'
                    }}
                </p>
            </div>

            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Sender customer
                </p>
                <p
                    class="mt-1 font-mono text-sm text-slate-950 dark:text-white"
                >
                    {{ profile.sender_customer_id || 'Not configured' }}
                </p>
            </div>
        </div>

        <div
            class="mt-4 rounded-2xl border p-3 text-xs leading-5"
            :class="
                profile.source_account_readiness?.ready
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                    : profile.source_account_readiness?.enabled
                      ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200'
                      : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
            "
        >
            <p class="font-semibold">
                Source account readiness:
                {{
                    profile.source_account_readiness?.enabled
                        ? profile.source_account_readiness?.ready
                            ? 'Ready'
                            : 'Needs check'
                        : 'Disabled'
                }}
            </p>
            <p class="mt-1">
                {{
                    profile.source_account_readiness?.message ||
                    'No source-account readiness message.'
                }}
            </p>
            <p v-if="profile.source_account_readiness?.checked" class="mt-1">
                Available:
                {{
                    formatMinorMoney(
                        profile.source_account_readiness
                            ?.available_balance_minor,
                        profile.source_account_readiness?.currency || 'PHP',
                    )
                }}
            </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Funding destination changes are PIN-protected and managed in
                Cockpit Accounts.
            </p>
            <Link
                :href="accountsIndex()"
                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
            >
                Manage Accounts
            </Link>
        </div>
    </section>
</template>
