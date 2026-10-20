<script setup lang="ts">
import { computed } from "vue";

type Report = { data: Record<string, any>[]; meta: Record<string, any> };
type Reports = { summary: Report; waterfall: Report; products: Report; positions: Report; movements: Report; exceptions: Report };

const props = defineProps<{ title: string; filters: { period: string; currency: string }; reports: Reports; operationsUrl: string }>();
const summary = computed(() => props.reports.summary.data[0] ?? {});
const money = (minor: number | null | undefined) => minor == null ? "—" : new Intl.NumberFormat("en-PH", { style: "currency", currency: props.filters.currency }).format(minor / 100);
const periodUrl = (period: string) => `${props.operationsUrl}?period=${encodeURIComponent(period)}`;
const max = (rows: Record<string, any>[], key: string) => Math.max(1, ...rows.map((row) => Number(row[key] ?? 0)));
const waterfallMax = computed(() => max(props.reports.waterfall.data, "amount_minor"));
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
      <header class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-widest text-sky-700">x-change · read only</p><h1 class="mt-2 text-3xl font-black">{{ title }}</h1><p class="mt-2 text-sm text-slate-600">Throughput, liability, revenue, positions, movements, and exceptions.</p></div>
        <nav class="flex gap-2 rounded-xl border border-slate-200 bg-white p-1 shadow-sm"><a v-for="period in [{ key: 'today', label: 'Today' }, { key: 'seven_days', label: '7 days' }, { key: 'thirty_days', label: '30 days' }]" :key="period.key" :href="periodUrl(period.key)" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="filters.period === period.key ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100'">{{ period.label }}</a></nav>
      </header>

      <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <article v-for="card in [{ label: 'Checkouts', value: summary.checkouts ?? 0 }, { label: 'Payments received', value: summary.payments_received ?? 0 }, { label: 'Pay Codes issued', value: summary.orders_issued ?? 0 }, { label: 'Claims', value: summary.claims ?? 0 }, { label: 'Exceptions', value: summary.exceptions ?? 0 }]" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">{{ card.label }}</p><p class="mt-3 text-3xl font-black">{{ card.value }}</p></article>
      </section>

      <section class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="card in [{ label: 'Pay Code principal', value: money(summary.principal_minor) }, { label: 'Commercial sales', value: money(summary.commercial_sales_minor) }, { label: 'Provider costs', value: money(summary.provider_cost_minor) }, { label: 'Product revenue', value: money(summary.product_revenue_minor) }]" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-500">{{ card.label }}</p><p class="mt-3 text-2xl font-black">{{ card.value }}</p><p class="mt-1 text-xs text-slate-400">Minor-unit read model · {{ summary.state }}</p></article>
      </section>

      <section class="mt-6 grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Revenue waterfall</h2><p class="text-sm text-slate-500">Allocation lines by destination and state.</p><div class="mt-6 space-y-4"><div v-for="row in reports.waterfall.data" :key="`${row.category}-${row.destination}-${row.status}`"><div class="flex justify-between text-sm"><span class="font-semibold">{{ row.category }} · {{ row.destination }}</span><span>{{ money(row.amount_minor) }}</span></div><div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-sky-600" :style="{ width: `${(row.amount_minor / waterfallMax) * 100}%` }" /></div><p class="mt-1 text-xs text-slate-400">{{ row.status }} · {{ row.line_count }} lines</p></div><p v-if="reports.waterfall.data.length === 0" class="text-sm text-slate-500">No allocation lines in this period.</p></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Product revenue</h2><p class="text-sm text-slate-500">Product attribution remains separate from principal.</p><div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b text-xs uppercase text-slate-500"><tr><th class="py-2 pr-4">Product</th><th class="py-2 pr-4">Status</th><th class="py-2">Amount</th></tr></thead><tbody><tr v-for="row in reports.products.data" :key="`${row.product}-${row.status}`" class="border-b border-slate-100"><td class="py-3 pr-4 font-semibold">{{ row.product }}</td><td class="py-3 pr-4">{{ row.status }}</td><td class="py-3">{{ money(row.amount_minor) }}</td></tr></tbody></table><p v-if="reports.products.data.length === 0" class="py-6 text-sm text-slate-500">No Product revenue in this period.</p></div></article>
      </section>

      <section class="mt-4 grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold">Positions</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b text-xs uppercase text-slate-500"><tr><th class="py-2 pr-4">Position</th><th class="py-2 pr-4">State</th><th class="py-2">Amount</th></tr></thead><tbody><tr v-for="row in reports.positions.data" :key="`${row.position}-${row.status}`" class="border-b border-slate-100"><td class="py-3 pr-4 font-mono text-xs">{{ row.position }}</td><td class="py-3 pr-4">{{ row.status }}</td><td class="py-3">{{ money(row.amount_minor) }}</td></tr></tbody></table><p v-if="reports.positions.data.length === 0" class="py-6 text-sm text-slate-500">No position movements in this period.</p></div></article>
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm"><h2 class="text-lg font-bold text-amber-950">Exceptions</h2><p class="text-sm text-amber-800">These are read-only reconciliation signals. No movement is executed here.</p><ul class="mt-4 space-y-3 text-sm"><li v-for="row in reports.exceptions.data" :key="row.reference" class="rounded-xl border border-amber-200 bg-white p-3"><span class="font-mono text-xs">{{ row.reference }}</span><span class="ml-2 font-semibold">{{ row.status }}</span><span class="float-right">{{ money(row.variance_minor) }}</span></li><li v-if="reports.exceptions.data.length === 0" class="text-amber-800">No exceptions in this period.</li></ul></article>
      </section>
    </div>
  </main>
</template>
