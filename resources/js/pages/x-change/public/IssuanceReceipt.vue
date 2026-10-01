<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
  CheckCircle2,
  Clock3,
  ExternalLink,
  ReceiptText,
  ShieldCheck,
} from "lucide-vue-next";

defineOptions({ layout: null });

type Receipt = {
  schema: "x-change.public-issuance-receipt.v1";
  order_reference: string;
  status: string;
  amount_minor: number;
  currency: string;
  created_at: string | null;
  verified_at: string | null;
  issued_at: string | null;
  pay_code: { code: string; claim_url: string } | null;
  activity: Array<{
    sequence: number;
    status: string;
    occurred_at: string | null;
  }>;
};

const props = defineProps<{ receipt: Receipt }>();

function money(amountMinor: number, currency: string): string {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency,
  }).format(amountMinor / 100);
}

function dateTime(value: string | null): string {
  return value === null ? "Not recorded" : new Date(value).toLocaleString();
}
</script>

<template>
  <Head title="Pay Code issuance receipt" />
  <main
    class="min-h-svh bg-slate-50 px-4 py-8 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
  >
    <article
      class="mx-auto w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/40 sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
      data-testid="public-issuance-receipt"
    >
      <div class="flex items-start justify-between gap-4">
        <div>
          <p
            class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
          >
            Pay Code record
          </p>
          <h1 class="mt-2 text-2xl font-black tracking-tight">
            Issuance receipt
          </h1>
        </div>
        <span
          class="grid size-11 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
          <ReceiptText class="size-5" aria-hidden="true" />
        </span>
      </div>

      <dl
        class="mt-7 grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950"
      >
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Order</dt>
          <dd class="break-all text-right font-mono text-xs">
            {{ receipt.order_reference }}
          </dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Amount paid</dt>
          <dd class="font-bold">
            {{ money(receipt.amount_minor, receipt.currency) }}
          </dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Status</dt>
          <dd class="font-bold capitalize">
            {{ receipt.status.replaceAll("_", " ") }}
          </dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Verified</dt>
          <dd class="text-right">{{ dateTime(receipt.verified_at) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-slate-500">Issued</dt>
          <dd class="text-right">{{ dateTime(receipt.issued_at) }}</dd>
        </div>
      </dl>

      <section
        v-if="receipt.pay_code"
        class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/40"
      >
        <p
          class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-300"
        >
          Pay Code ready
        </p>
        <p class="mt-2 font-mono text-3xl font-black tracking-[0.12em]">
          {{ receipt.pay_code.code }}
        </p>
        <Link
          :href="receipt.pay_code.claim_url"
          class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-full bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700"
        >
          Open Pay Code
          <ExternalLink class="size-4" aria-hidden="true" />
        </Link>
      </section>

      <section class="mt-7" aria-labelledby="receipt-activity-title">
        <h2 id="receipt-activity-title" class="text-sm font-bold">Activity</h2>
        <ol class="mt-3 grid gap-2">
          <li
            v-for="event in receipt.activity"
            :key="event.sequence"
            class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800"
          >
            <CheckCircle2
              class="mt-0.5 size-4 shrink-0 text-emerald-600"
              aria-hidden="true"
            />
            <span class="min-w-0 flex-1 capitalize">{{
              event.status.replaceAll("_", " ")
            }}</span>
            <span class="shrink-0 text-xs text-slate-500"
              ><Clock3 class="mr-1 inline size-3" />{{
                dateTime(event.occurred_at)
              }}</span
            >
          </li>
        </ol>
      </section>

      <p
        class="mt-7 flex items-start gap-2 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
      >
        <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        This read-only receipt excludes payer identity, provider payloads,
        credentials, and private Cockpit links.
      </p>
    </article>
  </main>
</template>
