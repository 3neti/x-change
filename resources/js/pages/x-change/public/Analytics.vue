<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { Activity, ArrowRight, BarChart3, CheckCircle2, Clock3, Layers3, Sparkles, TicketCheck } from "lucide-vue-next";
import PublicNavigation from "../../../cockpit/components/PublicNavigation.vue";

defineOptions({ layout: null });

type Navigation = { pricing_url: string | null; analytics_url: string | null; claim_url: string | null; create_url: string | null; login_url: string | null; register_url: string | null };
type Analytics = {
  period: string;
  available_periods: { key: string; label: string }[];
  totals: { batches: number; recipients: number; issued: number; issued_value_minor: number; claimed: number; claimed_value_minor: number; claim_rate: number };
  activity: { date: string; issued: number; claimed: number }[];
  lifecycle: { key: string; label: string; value: number }[];
  funding_methods: { key: string; label: string; value: number }[];
  batch_sizes: { label: string; value: number }[];
  batch_completion: { label: string; value: number }[];
};

const props = defineProps<{ analytics: Analytics; public_navigation: Navigation }>();
const money = (minor: number) => new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(minor / 100);
const maxActivity = computed(() => Math.max(1, ...props.analytics.activity.flatMap((day) => [day.issued, day.claimed])));
const maxLifecycle = computed(() => Math.max(1, ...props.analytics.lifecycle.map((item) => item.value)));
const maxFunding = computed(() => Math.max(1, ...props.analytics.funding_methods.map((item) => item.value)));
const maxSizes = computed(() => Math.max(1, ...props.analytics.batch_sizes.map((item) => item.value)));
const maxCompletion = computed(() => Math.max(1, ...props.analytics.batch_completion.map((item) => item.value)));
function periodUrl(key: string): string { return `${props.public_navigation.analytics_url ?? "/x/analytics"}?period=${encodeURIComponent(key)}`; }
function shortDate(value: string): string { return new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" }); }
</script>

<template>
  <Head title="Public activity" />
  <main class="min-h-svh overflow-hidden bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50" data-testid="public-analytics-page">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[30rem] bg-[radial-gradient(circle_at_15%_15%,rgba(16,185,129,0.16),transparent_38%),radial-gradient(circle_at_85%_0%,rgba(14,165,233,0.12),transparent_34%)]" aria-hidden="true" />
    <div class="relative z-10 mx-auto w-full max-w-[90rem] px-4 sm:px-6 lg:px-8">
      <PublicNavigation :navigation="public_navigation" active="analytics" />
      <header class="flex flex-col gap-6 py-10 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
          <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200"><Activity class="size-3.5" /> Public activity</p>
          <h1 class="text-4xl font-black tracking-tight sm:text-5xl">A clear view of Pay Code distribution.</h1>
          <p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">Aggregate beta activity only. No names, mobile numbers, account details, claim URLs, or provider payloads are shown here.</p>
        </div>
        <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white/80 p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900/80" data-testid="analytics-periods">
          <Link v-for="period in analytics.available_periods" :key="period.key" :href="periodUrl(period.key)" class="rounded-xl px-3 py-2 text-sm font-semibold transition" :class="analytics.period === period.key ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'">{{ period.label }}</Link>
        </div>
      </header>

      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="analytics-kpis">
        <article v-for="card in [
          { label: 'Batches', value: analytics.totals.batches, detail: 'distribution runs', icon: Layers3, tone: 'emerald' },
          { label: 'Pay Codes issued', value: analytics.totals.issued, detail: money(analytics.totals.issued_value_minor), icon: TicketCheck, tone: 'sky' },
          { label: 'Pay Codes claimed', value: analytics.totals.claimed, detail: money(analytics.totals.claimed_value_minor), icon: CheckCircle2, tone: 'violet' },
          { label: 'Claim rate', value: `${analytics.totals.claim_rate}%`, detail: `${analytics.totals.recipients} recipients`, icon: Sparkles, tone: 'amber' },
        ]" :key="card.label" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <div class="flex items-start justify-between"><p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ card.label }}</p><component :is="card.icon" class="size-5 text-emerald-600" /></div>
          <p class="mt-5 text-3xl font-black tracking-tight">{{ card.value }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ card.detail }}</p>
        </article>
      </section>

      <section class="mt-4 grid gap-4 lg:grid-cols-[1.4fr_0.6fr]">
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" data-testid="analytics-activity-chart">
          <div class="flex items-center justify-between"><div><p class="text-lg font-bold">Issuance momentum</p><p class="text-sm text-slate-500 dark:text-slate-400">Issued and claimed Pay Codes over time</p></div><BarChart3 class="size-5 text-slate-400" /></div>
          <div class="mt-8 flex h-56 items-end gap-1 border-b border-slate-200 pb-0 dark:border-slate-700 sm:gap-2">
            <div v-for="day in analytics.activity" :key="day.date" class="group flex h-full min-w-0 flex-1 items-end justify-center gap-0.5" :title="`${day.date}: ${day.issued} issued, ${day.claimed} claimed`">
              <div class="w-1/2 rounded-t bg-emerald-500 transition group-hover:bg-emerald-400" :style="{ height: `${Math.max(day.issued ? 4 : 0, (day.issued / maxActivity) * 100)}%` }" />
              <div class="w-1/2 rounded-t bg-sky-400 transition group-hover:bg-sky-300" :style="{ height: `${Math.max(day.claimed ? 4 : 0, (day.claimed / maxActivity) * 100)}%` }" />
            </div>
          </div>
          <div class="mt-3 flex justify-between text-[0.65rem] text-slate-400"><span v-for="day in analytics.activity.filter((_, index) => index === 0 || index === analytics.activity.length - 1 || index % 7 === 0)" :key="day.date">{{ shortDate(day.date) }}</span></div>
          <div class="mt-5 flex gap-5 text-xs font-semibold text-slate-500"><span><i class="mr-1 inline-block size-2 rounded-full bg-emerald-500" />Issued</span><span><i class="mr-1 inline-block size-2 rounded-full bg-sky-400" />Claimed</span></div>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" data-testid="analytics-lifecycle-chart"><p class="text-lg font-bold">Lifecycle</p><p class="text-sm text-slate-500 dark:text-slate-400">Where distribution stands</p><div class="mt-8 space-y-5"><div v-for="item in analytics.lifecycle" :key="item.key"><div class="mb-2 flex justify-between text-sm"><span class="font-semibold">{{ item.label }}</span><span class="text-slate-500">{{ item.value }}</span></div><div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${(item.value / maxLifecycle) * 100}%` }" /></div></div></div></article>
      </section>

      <section class="mt-4 grid gap-4 md:grid-cols-3">
        <article v-for="panel in [
          { testid: 'analytics-funding-chart', title: 'Funding methods', subtitle: 'Batches by selected rail', rows: analytics.funding_methods, max: maxFunding },
          { testid: 'analytics-size-chart', title: 'Batch size', subtitle: 'How many recipients per batch', rows: analytics.batch_sizes, max: maxSizes },
          { testid: 'analytics-completion-chart', title: 'Time to issue', subtitle: 'From batch creation to issuance', rows: analytics.batch_completion, max: maxCompletion },
        ]" :key="panel.testid" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" :data-testid="panel.testid"><p class="text-lg font-bold">{{ panel.title }}</p><p class="text-sm text-slate-500 dark:text-slate-400">{{ panel.subtitle }}</p><div class="mt-6 space-y-4"><div v-for="row in panel.rows" :key="row.label"><div class="mb-1.5 flex justify-between text-xs font-semibold"><span>{{ row.label }}</span><span class="text-slate-500">{{ row.value }}</span></div><div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-slate-900 dark:bg-slate-100" :style="{ width: `${(row.value / panel.max) * 100}%` }" /></div></div></div><p v-if="panel.rows.length === 0" class="mt-8 text-sm text-slate-500">No activity in this period yet.</p></article>
      </section>

      <footer class="flex flex-col items-start justify-between gap-4 py-12 text-sm text-slate-500 sm:flex-row sm:items-center"><p class="inline-flex items-center gap-2"><Clock3 class="size-4" /> Updated from a short-lived aggregate cache.</p><Link v-if="public_navigation.create_url" :href="public_navigation.create_url" class="inline-flex items-center gap-2 font-bold text-emerald-700 hover:text-emerald-800 dark:text-emerald-300">Create a Pay Code <ArrowRight class="size-4" /></Link></footer>
    </div>
  </main>
</template>
