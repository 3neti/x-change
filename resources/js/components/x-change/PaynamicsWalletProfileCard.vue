<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as accountsIndex } from '@/routes/x-change/cockpit/accounts';

type BalanceLine = {
    balance?: number | string | null;
    currency?: string | null;
    checked_at?: string | null;
    provider_wallet_id?: string | null;
    sync_status?: string | null;
    sync_message?: string | null;
};

type PaynamicsWalletProfile = {
    wallet_id?: string | null;
    status?: string | null;
    verification_status?: string | null;
    identity_level?: string | null;
    ownership_verification_required?: boolean;
    ownership_verification_note?: string | null;
    balance_overview?: {
        authoritative?: BalanceLine | null;
        sync_status?: string | null;
        sync_message?: string | null;
    } | null;
};

const props = defineProps<{
    wallet: PaynamicsWalletProfile;
    status?: string;
}>();

const formatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

const formatMoney = (balance?: number | string | null) => {
    if (balance === null || balance === undefined || balance === '') {
        return 'Not available';
    }

    return formatter.format(Number(balance));
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
                    Provider account
                </p>
                <h2
                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-white"
                >
                    Paynamics account link
                </h2>
                <p
                    class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400"
                >
                    Link an existing Paynamics account ID so x-change can use
                    provider-authoritative balance checks for Pay Code issuance.
                </p>
            </div>

            <span
                class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold"
                :class="
                    wallet.status === 'ready'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300'
                "
            >
                {{ wallet.status === 'ready' ? 'Linked' : 'Not linked' }}
            </span>
        </div>

        <div
            class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3 dark:bg-slate-900"
        >
            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Provider account ID
                </p>
                <p
                    class="mt-1 font-mono text-sm break-all text-slate-950 dark:text-white"
                >
                    {{ wallet.wallet_id || 'None linked' }}
                </p>
            </div>

            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Balance
                </p>
                <p
                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-white"
                >
                    {{
                        formatMoney(
                            wallet.balance_overview?.authoritative?.balance,
                        )
                    }}
                </p>
            </div>

            <div>
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    Sync
                </p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                    {{ wallet.balance_overview?.sync_status || 'not_synced' }}
                </p>
            </div>
        </div>

        <p
            v-if="wallet.ownership_verification_required"
            class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            This scaffold only proves the provider account exists and can return a
            balance. Ownership verification is still pending:
            {{ wallet.ownership_verification_note }}
        </p>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                This profile is a summary. Linking or selecting a provider account
                is PIN-protected in Cockpit Accounts.
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
