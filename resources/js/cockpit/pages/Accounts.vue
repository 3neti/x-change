<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import {
  ArrowRight,
  Building2,
  Landmark,
  Plus,
  ReceiptText,
  ShieldCheck,
} from "lucide-vue-next";
import { index as fundingIndex } from "@/routes/x-change/cockpit/funding";
import { quickGenerate } from "@/routes/x-change/cockpit";
import { store as storeBankAccount, update as updateBankAccount } from "@/routes/x-change/cockpit/accounts/bank-accounts";
import { computed, ref } from "vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type { CockpitAccountsPageProps } from "../types";

const props = defineProps<CockpitAccountsPageProps>();
const showAddAccount = ref(false);
const accountForm = useForm({ owner_id: "", nickname: "", account_name: "", account_number: "", customer_id: "", vca_alias: "" });
const defaultForm = useForm<{ account?: string }>({});
const nicknameForm = useForm({ action: "rename", nickname: "" });
const editingAccount = ref<number | null>(null);
const configuringAccount = ref<number | null>(null);
const configureForm = useForm({ action: "configure", treasury_connection: "", disbursement_confirmed: false });
const fundingSetupAccount = ref<number | null>(null);
const fundingSetupForm = useForm({ action: "configure_funding", history_reviewed: false });
const freshFundingForm = useForm({ action: "configure_fresh_funding", sole_writer_confirmed: false, known_vcas: "" });

function configureFreshFunding(id: number): void {
  freshFundingForm.transform((data) => ({
    ...data,
    known_vcas: data.known_vcas.split(/[\s,]+/).filter(Boolean),
  })).patch(updateBankAccount(id).url, {
    preserveScroll: true,
    onSuccess: () => { freshFundingForm.reset(); fundingSetupAccount.value = null; },
  });
}


function toggleFundingSetup(id: number): void {
  const opening = fundingSetupAccount.value !== id;
  fundingSetupForm.reset();
  fundingSetupForm.clearErrors();
  freshFundingForm.reset();
  freshFundingForm.clearErrors();
  fundingSetupAccount.value = opening ? id : null;
}

function configureFunding(id: number): void {
  fundingSetupForm.patch(updateBankAccount(id).url, {
    preserveScroll: true,
    onSuccess: () => { fundingSetupForm.reset(); fundingSetupAccount.value = null; },
  });
}

function configureAccount(id: number): void {
  configureForm.patch(updateBankAccount(id).url, {
    preserveScroll: true,
    onSuccess: () => { configureForm.reset(); configuringAccount.value = null; },
  });
}

function addBankAccount(): void {
  accountForm.post(storeBankAccount().url, {
    preserveScroll: true,
    onSuccess: () => { accountForm.reset(); showAddAccount.value = false; },
  });
}

function selectDefaultAccount(id: number): void {
  defaultForm.patch(updateBankAccount(id).url, { preserveScroll: true });
}

function editNickname(id: number, nickname: string | null): void {
  editingAccount.value = id;
  nicknameForm.nickname = nickname || "";
}

function saveNickname(id: number): void {
  nicknameForm.patch(updateBankAccount(id).url, {
    preserveScroll: true,
    onSuccess: () => { editingAccount.value = null; },
  });
}

const readyDestinationCount = computed(
  () =>
    props.account_overview.funding_destinations.filter(
      (destination) => destination.status === "ready",
    ).length,
);

function displayLabel(value: string): string {
  return value
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
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
      <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
        data-testid="account-overview"
      >
        <div
          class="grid gap-5 bg-slate-950 px-5 py-5 text-white lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-6"
        >
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="text-xs font-semibold tracking-[0.18em] text-sky-300 uppercase"
              >
                Account
              </p>
              <span
                class="rounded-full border border-emerald-300/25 bg-emerald-300/10 px-2.5 py-1 text-[0.68rem] font-semibold text-emerald-200"
              >
                {{ account_overview.account.currency }} · Ready
              </span>
            </div>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
              Your Account
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
              Fund this Account, then create and track your Pay Codes from one
              place.
            </p>
          </div>
          <div class="flex flex-col gap-2 sm:flex-row">
            <Link
              :href="fundingIndex()"
              class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-white/15 bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-sky-50"
              data-testid="account-add-funds"
            >
              <Plus class="size-4" aria-hidden="true" />
              Add Funds
            </Link>
            <Link
              :href="quickGenerate()"
              class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-500 px-4 text-sm font-semibold text-slate-950 transition hover:bg-sky-400"
              data-testid="account-create-pay-code"
            >
              <ReceiptText class="size-4" aria-hidden="true" />
              Create Pay Code
            </Link>
          </div>
        </div>

        <dl
          class="grid gap-px bg-slate-200 text-sm sm:grid-cols-3 dark:bg-slate-800"
          data-testid="account-identity"
        >
          <div class="bg-white px-5 py-3.5 dark:bg-slate-950">
            <dt class="text-xs text-slate-500">Account Reference</dt>
            <dd class="mt-1 font-semibold text-slate-950 dark:text-white">
              {{ account_overview.account.reference }}
            </dd>
          </div>
          <div class="bg-white px-5 py-3.5 dark:bg-slate-950">
            <dt class="text-xs text-slate-500">Currency</dt>
            <dd class="mt-1 font-semibold text-slate-950 dark:text-white">
              {{ account_overview.account.currency }}
            </dd>
          </div>
          <div class="bg-white px-5 py-3.5 dark:bg-slate-950">
            <dt class="text-xs text-slate-500">Funding Destinations</dt>
            <dd class="mt-1 font-semibold text-slate-950 dark:text-white">
              {{ readyDestinationCount }} Ready
            </dd>
          </div>
        </dl>
      </section>

      <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        data-testid="connected-bank-accounts"
      >
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-950 dark:text-white">Connected bank accounts</h2>
          <button v-if="can_manage_bank_accounts" type="button" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-900" @click="showAddAccount = !showAddAccount">{{ showAddAccount ? 'Cancel' : 'Add account' }}</button>
        </div>
        <p v-if="!bank_accounts.length && !can_manage_bank_accounts" class="mt-3 text-sm text-slate-600 dark:text-slate-400">No personal bank accounts connected. Contact your Treasury administrator to add one.</p>
        <form v-if="showAddAccount" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="addBankAccount">
          <label class="text-sm">User ID (leave blank for your account)<input v-model="accountForm.owner_id" inputmode="numeric" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <label class="text-sm">Nickname (optional)<input v-model="accountForm.nickname" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <label class="text-sm">Registered account name<input v-model="accountForm.account_name" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <label class="text-sm">Account number<input v-model="accountForm.account_number" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <label class="text-sm">Customer ID<input v-model="accountForm.customer_id" required inputmode="numeric" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <label class="text-sm">VCA alias<input v-model="accountForm.vca_alias" required inputmode="numeric" maxlength="5" class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" /></label>
          <p v-if="Object.keys(accountForm.errors).length" class="text-sm text-red-700" role="alert">{{ Object.values(accountForm.errors)[0] }}</p>
          <button type="submit" :disabled="accountForm.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Add account</button>
        </form>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <article v-for="account in bank_accounts" :key="account.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold text-slate-950 dark:text-white">{{ account.nickname || account.account_name }}</h3>
              <span v-if="account.is_default" class="text-xs font-semibold text-sky-700 dark:text-sky-300">Default</span>
            </div>
            <p v-if="account.owner_id" class="mt-1 text-xs text-slate-500">User {{ account.owner_id }}</p>
            <p v-if="account.nickname" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ account.account_name }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ account.account_number }}</p>
            <p class="mt-3 text-xs text-slate-500">
              Funding: {{ account.funding_ready ? 'Ready' : 'Not ready' }} · Issuance: {{ account.disbursement_ready ? 'Ready' : 'Not ready' }}
            </p>
            <p v-if="!can_configure_bank_accounts && !account.funding_ready" class="mt-2 text-xs text-slate-600 dark:text-slate-400">Ask your Treasury administrator to finish setting up funding for this account.</p>
            <p v-if="!can_configure_bank_accounts && !account.disbursement_ready" class="mt-2 text-xs text-slate-600 dark:text-slate-400">Ask your Treasury administrator to finish setting up issuance for this account.</p>
            <button v-if="account.is_owner !== false && (account.funding_ready || account.disbursement_ready) && !account.is_default" type="button" :disabled="defaultForm.processing" class="mt-3 text-sm font-semibold text-sky-700 dark:text-sky-300" @click="selectDefaultAccount(account.id)">Set as default</button>
            <button v-if="account.is_owner !== false" type="button" class="mt-3 ml-3 text-sm font-semibold text-sky-700 dark:text-sky-300" @click="editNickname(account.id, account.nickname)">Edit nickname</button>
            <button v-if="can_configure_bank_accounts && !account.disbursement_ready" type="button" class="mt-3 ml-3 text-sm font-semibold text-sky-700 dark:text-sky-300" @click="configuringAccount = configuringAccount === account.id ? null : account.id">Set up issuance</button>
            <button v-if="can_configure_bank_accounts && personal_netbank_funding_enabled && !account.funding_ready" type="button" class="mt-3 ml-3 text-sm font-semibold text-sky-700 dark:text-sky-300" @click="toggleFundingSetup(account.id)">Set up funding</button>
            <form v-if="can_configure_bank_accounts && personal_netbank_funding_enabled && fundingSetupAccount === account.id" class="mt-3 space-y-3" @submit.prevent="configureFunding(account.id)">
              <label class="flex gap-2 text-sm"><input v-model="fundingSetupForm.history_reviewed" type="checkbox" required /> I have reviewed and recorded previously used VCA references for this account.</label>
              <button type="submit" :disabled="fundingSetupForm.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Enable funding</button>
              <p v-if="Object.keys(fundingSetupForm.errors).length" role="alert" class="text-sm text-red-700">{{ Object.values(fundingSetupForm.errors)[0] }}</p>
            </form>
            <form v-if="personal_netbank_fresh_commissioning_available && fundingSetupAccount === account.id" class="mt-4 space-y-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700" @submit.prevent="configureFreshFunding(account.id)">
              <p class="text-sm font-semibold">New funding requests only</p>
              <p class="text-sm text-slate-600 dark:text-slate-400">Previously received payments and standing addresses remain separate and are not reconciled by this setup.</p>
              <label class="block text-sm">Previously generated NetBank VCAs (12 or 16 digits), separated by spaces or commas
                <textarea v-model="freshFundingForm.known_vcas" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900" />
              </label>
              <p class="text-xs text-slate-600 dark:text-slate-400">New addresses use the 12-digit alias-and-reference format. Historical mobile-referenced addresses have 16 digits.</p>
              <label class="flex gap-2 text-sm"><input v-model="freshFundingForm.sole_writer_confirmed" type="checkbox" required /> This is the only system generating new seven-digit references for this alias.</label>
              <button type="submit" :disabled="freshFundingForm.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Enable new funding</button>
              <p v-if="Object.keys(freshFundingForm.errors).length" role="alert" class="text-sm text-red-700">{{ Object.values(freshFundingForm.errors)[0] }}</p>
            </form>
            <form v-if="configuringAccount === account.id" class="mt-3 space-y-3" @submit.prevent="configureAccount(account.id)">
              <p v-if="!props.treasury_connections?.length" class="text-sm text-amber-700">A separate treasury connection must be configured before this account can be set up.</p>
              <template v-else>
                <label class="block text-sm">Treasury connection
                  <select v-model="configureForm.treasury_connection" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2 dark:bg-slate-900">
                    <option value="" disabled>Select a connection</option>
                    <option v-for="connection in props.treasury_connections" :key="connection" :value="connection">{{ connection }}</option>
                  </select>
                </label>
                <label class="flex gap-2 text-sm"><input v-model="configureForm.disbursement_confirmed" type="checkbox" required /> I confirm this bank account is permitted to send payouts using these provider credentials.</label>
                <button type="submit" :disabled="configureForm.processing" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Check and set up</button>
              </template>
              <p v-if="Object.keys(configureForm.errors).length" role="alert" class="text-sm text-red-700">{{ Object.values(configureForm.errors)[0] }}</p>
            </form>
            <form v-if="editingAccount === account.id" class="mt-3 flex gap-2" @submit.prevent="saveNickname(account.id)">
              <label class="sr-only" :for="`nickname-${account.id}`">Account nickname</label>
              <input :id="`nickname-${account.id}`" v-model="nicknameForm.nickname" maxlength="191" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-sm dark:bg-slate-900" />
              <button type="submit" :disabled="nicknameForm.processing" class="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-slate-900">Save</button>
            </form>
            <p v-if="defaultForm.errors.account" role="alert" class="mt-2 text-sm text-red-700">{{ defaultForm.errors.account }}</p>
          </article>
        </div>
      </section>

      <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        data-testid="account-funding-destinations"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <Landmark class="size-4 text-sky-600" aria-hidden="true" />
              <h2
                class="text-base font-semibold text-slate-950 dark:text-white"
              >
                Funding Destinations
              </h2>
            </div>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
              Available when adding funds to this Account.
            </p>
          </div>
          <span
            class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
          >
            {{ readyDestinationCount }} Ready
          </span>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <article
            v-for="destination in account_overview.funding_destinations"
            :key="destination.code"
            class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-800"
          >
            <div class="flex min-w-0 items-center gap-3">
              <div
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300"
              >
                <Building2 class="size-4" aria-hidden="true" />
              </div>
              <div class="min-w-0">
                <h3
                  class="text-sm font-semibold text-slate-950 dark:text-white"
                >
                  {{ destination.label }}
                </h3>
                <p class="truncate text-xs text-slate-500">
                  {{ destination.display_reference ?? "Not connected" }}
                </p>
              </div>
            </div>
            <span
              class="shrink-0 rounded-full px-2 py-1 text-[0.68rem] font-semibold"
              :class="
                destination.status === 'ready'
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                  : 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300'
              "
            >
              {{ displayLabel(destination.status) }}
            </span>
          </article>
        </div>
      </section>

      <section class="grid gap-3 sm:grid-cols-2" aria-label="Account actions">
        <Link
          :href="fundingIndex()"
          class="group flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-sky-800"
        >
          <span class="flex items-center gap-3">
            <span
              class="flex size-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300"
            >
              <Landmark class="size-5" aria-hidden="true" />
            </span>
            <span>
              <span
                class="block text-sm font-semibold text-slate-950 dark:text-white"
                >Funding</span
              >
              <span class="mt-0.5 block text-xs text-slate-500"
                >QR Ph, bank transfer, or Pay Code</span
              >
            </span>
          </span>
          <ArrowRight
            class="size-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-600"
            aria-hidden="true"
          />
        </Link>
        <Link
          :href="quickGenerate()"
          class="group flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-sky-800"
        >
          <span class="flex items-center gap-3">
            <span
              class="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300"
            >
              <ReceiptText class="size-5" aria-hidden="true" />
            </span>
            <span>
              <span
                class="block text-sm font-semibold text-slate-950 dark:text-white"
                >Issuance</span
              >
              <span class="mt-0.5 block text-xs text-slate-500"
                >Design and issue a Pay Code</span
              >
            </span>
          </span>
          <ArrowRight
            class="size-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-600"
            aria-hidden="true"
          />
        </Link>
      </section>

      <div
        class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
        data-testid="account-settlement-boundary"
      >
        <ShieldCheck
          class="mt-0.5 size-4 shrink-0 text-emerald-600"
          aria-hidden="true"
        />
        <p>
          Funds are added only after confirmation from the bank or payment
          provider. Account and provider credentials remain private.
        </p>
      </div>
    </div>
  </CockpitLayout>
</template>
