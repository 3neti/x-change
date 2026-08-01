<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
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
import { computed } from "vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type { CockpitAccountsPageProps } from "../types";

const props = defineProps<CockpitAccountsPageProps>();

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
