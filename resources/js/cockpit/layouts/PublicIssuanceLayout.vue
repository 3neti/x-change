<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
  ArrowRight,
  BadgeCheck,
  BellRing,
  CheckCircle2,
  Link2,
  MessageSquareText,
  MousePointerClick,
  ReceiptText,
  Send,
  ShieldCheck,
} from "lucide-vue-next";

withDefaults(
  defineProps<{
    navigation?: {
      claim_url: string | null;
      login_url: string | null;
      register_url: string | null;
    };
  }>(),
  {
    navigation: () => ({
      claim_url: null,
      login_url: null,
      register_url: null,
    }),
  },
);

const painPoints = [
  "No asking for someone’s mobile number",
  "No transaction screenshot to send and verify",
  "No guessing whether the Pay Code was claimed",
];
</script>

<template>
  <Head title="Create a Pay Code in seconds" />
  <main
    class="min-h-svh overflow-hidden bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
    data-testid="public-issuance-layout"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[34rem] bg-[radial-gradient(circle_at_15%_15%,rgba(16,185,129,0.16),transparent_38%),radial-gradient(circle_at_85%_0%,rgba(14,165,233,0.12),transparent_34%)]"
      aria-hidden="true"
    />

    <div
      class="relative z-10 mx-auto w-full max-w-[90rem] px-4 sm:px-6 lg:px-8"
    >
      <nav
        class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200/70 dark:border-slate-800"
        aria-label="Public issuance"
      >
        <Link
          :href="navigation.claim_url ?? '#'"
          class="inline-flex items-center gap-2 text-sm font-black tracking-tight text-slate-950 dark:text-white"
          data-testid="public-auto-generate-brand"
        >
          <span
            class="grid size-9 place-items-center rounded-xl bg-slate-950 text-sm text-white shadow-sm dark:bg-white dark:text-slate-950"
            aria-hidden="true"
            >||</span
          >
          <span>Pay Code</span>
        </Link>

        <div class="flex items-center gap-2 text-sm font-semibold">
          <Link
            v-if="navigation.claim_url"
            :href="navigation.claim_url"
            class="hidden rounded-full px-3 py-2 text-slate-600 transition hover:bg-white hover:text-slate-950 sm:inline-flex dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white"
          >
            Claim a Pay Code
          </Link>
          <Link
            v-if="navigation.login_url"
            :href="navigation.login_url"
            class="inline-flex rounded-full border border-slate-300 bg-white px-4 py-2 text-slate-700 shadow-sm transition hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            data-testid="public-auto-generate-sign-in"
          >
            Sign in
          </Link>
        </div>
      </nav>

      <section
        class="grid items-start gap-8 py-9 lg:grid-cols-[minmax(0,0.82fr)_minmax(36rem,1.18fr)] lg:gap-12 lg:py-14"
      >
        <header
          class="pt-2 lg:sticky lg:top-6 lg:pt-8"
          data-testid="public-auto-generate-header"
        >
          <p
            class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200"
          >
            No x-change Account required
          </p>
          <h1
            class="mt-5 max-w-xl text-4xl font-black tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl dark:text-white"
          >
            Create a Pay Code in seconds.
          </h1>
          <p
            class="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300"
          >
            Skip asking for bank or wallet details. Share a Pay Code and let the
            recipient claim it to the account they choose.
          </p>
          <p
            class="mt-5 max-w-xl border-l-2 border-emerald-500 pl-4 text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
          >
            Let the meaning arrive with the money.
          </p>

          <ul class="mt-7 grid gap-3" aria-label="Pay Code advantages">
            <li
              v-for="item in painPoints"
              :key="item"
              class="flex items-start gap-2.5 text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
              <CheckCircle2
                class="mt-1 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                aria-hidden="true"
              />
              {{ item }}
            </li>
          </ul>
        </header>

        <div
          class="rounded-[1.75rem] border border-slate-200/80 bg-white/85 py-5 shadow-[0_24px_80px_-44px_rgba(15,23,42,0.55)] backdrop-blur sm:p-6 dark:border-slate-800 dark:bg-slate-900/80"
          data-testid="public-auto-generate-tool"
        >
          <div class="mb-4 px-4 sm:px-0">
            <p
              class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
            >
              Quick tool
            </p>
            <h2 class="mt-1 text-xl font-bold tracking-tight">
              Build your Pay Code
            </h2>
            <p
              class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
              Add value and optional claim instructions. Review the exact total
              before paying.
            </p>
          </div>
          <slot />
        </div>
      </section>

      <section
        class="border-y border-slate-200 py-12 dark:border-slate-800"
        aria-labelledby="public-how-it-works"
      >
        <div class="max-w-2xl">
          <p
            class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
          >
            Pull, not push
          </p>
          <h2
            id="public-how-it-works"
            class="mt-2 text-3xl font-black tracking-tight"
          >
            Give the recipient control of the last mile.
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
            You create the instruction. They claim when ready and choose the
            supported account that works for them.
          </p>
        </div>

        <div class="mt-7 grid gap-3 md:grid-cols-3">
          <article
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
          >
            <Send class="size-5 text-emerald-600" aria-hidden="true" />
            <h3 class="mt-4 font-bold">1. Create</h3>
            <p
              class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
              Set the value, recipient safeguards, and what the claim should
              mean.
            </p>
          </article>
          <article
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
          >
            <MousePointerClick
              class="size-5 text-emerald-600"
              aria-hidden="true"
            />
            <h3 class="mt-4 font-bold">2. Share</h3>
            <p
              class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
              Send the short code, claim link, or recognizable Pay Code QR.
            </p>
          </article>
          <article
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
          >
            <BadgeCheck class="size-5 text-emerald-600" aria-hidden="true" />
            <h3 class="mt-4 font-bold">3. Claim</h3>
            <p
              class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
              The recipient supplies only the information you requested, then
              completes the claim.
            </p>
          </article>
        </div>
      </section>

      <section class="grid gap-5 py-12 lg:grid-cols-2">
        <article
          class="rounded-3xl bg-slate-950 p-7 text-white dark:bg-white dark:text-slate-950"
        >
          <Link2
            class="size-6 text-emerald-400 dark:text-emerald-600"
            aria-hidden="true"
          />
          <p
            class="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-300 dark:text-emerald-700"
          >
            After-claim destination
          </p>
          <h2 class="mt-2 text-2xl font-black tracking-tight">
            Send more than money. Send them somewhere.
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-300 dark:text-slate-600">
            Add an optional destination to continue the moment on your website,
            video, music, article, social page, or thank-you experience after
            the claim.
          </p>
        </article>

        <article
          class="rounded-3xl border border-slate-200 bg-white p-7 dark:border-slate-800 dark:bg-slate-900"
        >
          <BellRing class="size-6 text-emerald-600" aria-hidden="true" />
          <p
            class="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
          >
            Optional status updates
          </p>
          <h2 class="mt-2 text-2xl font-black tracking-tight">
            Share it. They claim it. You know.
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
            Add an email address or mobile number to receive supported claim
            updates. Keep the Pay Code as the instruction and its activity as
            the record.
          </p>
          <div
            class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300"
          >
            <span
              class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800"
              ><MessageSquareText class="size-3.5" /> SMS</span
            >
            <span
              class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800"
              ><ReceiptText class="size-3.5" /> Claim activity</span
            >
          </div>
        </article>
      </section>

      <section
        class="mb-10 flex flex-col gap-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-7 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-900 dark:bg-emerald-950/40"
        data-testid="public-auto-generate-upgrade"
      >
        <div class="max-w-2xl">
          <p
            class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
          >
            Go professional
          </p>
          <h2 class="mt-2 text-2xl font-black tracking-tight">
            Turn one Pay Code into a repeatable workflow.
          </h2>
          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
            Save templates, manage campaigns, follow activity, and fund future
            instructions from one x-change Account.
          </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
          <Link
            v-if="navigation.register_url"
            :href="navigation.register_url"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-bold text-white transition hover:bg-emerald-700"
          >
            Open an Account
            <ArrowRight class="size-4" aria-hidden="true" />
          </Link>
          <Link
            v-if="navigation.login_url"
            :href="navigation.login_url"
            class="inline-flex min-h-11 items-center justify-center rounded-full border border-emerald-300 bg-white px-5 text-sm font-bold text-emerald-800 dark:border-emerald-800 dark:bg-slate-950 dark:text-emerald-200"
          >
            Sign in
          </Link>
        </div>
      </section>

      <footer
        class="flex items-center justify-between gap-4 border-t border-slate-200 py-7 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
      >
        <span class="inline-flex items-center gap-1.5"
          ><ShieldCheck class="size-3.5" aria-hidden="true" /> Pay Code issuance
          is completed only after payment verification.</span
        >
        <span class="hidden sm:inline">Powered by x-change</span>
      </footer>
    </div>
  </main>
</template>
