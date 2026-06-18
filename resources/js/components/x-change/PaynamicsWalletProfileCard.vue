<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import LinkPaynamicsWalletController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/LinkPaynamicsWalletController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
                    Provider wallet
                </p>
                <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">
                    Paynamics wallet link
                </h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                    Link an existing Paynamics wallet ID so x-change can use provider-authoritative balance checks for Pay Code issuance.
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

        <div class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Wallet ID
                </p>
                <p class="mt-1 break-all font-mono text-sm text-slate-950 dark:text-white">
                    {{ wallet.wallet_id || 'None linked' }}
                </p>
            </div>

            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Balance
                </p>
                <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">
                    {{ formatMoney(wallet.balance_overview?.authoritative?.balance) }}
                </p>
            </div>

            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
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
            This scaffold only proves the wallet exists and can return a balance. Ownership verification is still pending:
            {{ wallet.ownership_verification_note }}
        </p>

        <Form
            v-bind="LinkPaynamicsWalletController.form()"
            class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-2">
                <Label for="paynamics_wallet_id">Existing Paynamics wallet ID</Label>
                <Input
                    id="paynamics_wallet_id"
                    name="wallet_id"
                    inputmode="text"
                    autocomplete="off"
                    :default-value="wallet.wallet_id || ''"
                    placeholder="CNSTWLLT..."
                />
                <InputError :message="errors.wallet_id" />
            </div>

            <div class="flex items-end">
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Checking...' : 'Link wallet' }}
                </Button>
            </div>

            <p
                v-if="recentlySuccessful || status === 'paynamics-wallet-linked'"
                class="text-sm font-medium text-emerald-700 dark:text-emerald-300 sm:col-span-2"
            >
                Paynamics wallet linked and balance projection refreshed.
            </p>
        </Form>
    </section>
</template>
