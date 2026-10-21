<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

type PricingItem = {
  reference: string;
  label: string;
  category: string;
  currency: string;
  unit_price_minor: number;
  expected_provider_cost_minor: number;
  expected_provider_cost_basis: string;
  deprecated?: boolean;
};

const props = defineProps<{
  pricing: {
    currency: string;
    active_version: number;
    items: PricingItem[];
  };
}>();

const form = useForm({
  items: props.pricing.items.map((item) => ({
    reference: item.reference,
    unit_price: (item.unit_price_minor / 100).toFixed(2),
    expected_provider_cost: ((item.expected_provider_cost_minor ?? 0) / 100).toFixed(2),
    expected_provider_cost_basis: item.expected_provider_cost_basis ?? "unconfigured",
  })),
});

const uploadForm = useForm<{ pricing_file: File | null }>({
  pricing_file: null,
});

const changed = computed(() => form.isDirty);

const totals = computed(() => form.items.reduce((summary, item) => {
  const customerPrice = Number.parseFloat(item.unit_price) || 0;
  const providerCost = Number.parseFloat(item.expected_provider_cost) || 0;

  return {
    customerPrice: summary.customerPrice + customerPrice,
    providerCost: summary.providerCost + providerCost,
    spread: summary.spread + customerPrice - providerCost,
  };
}, { customerPrice: 0, providerCost: 0, spread: 0 }));

function formatAmount(amount: number): string {
  return `₱${amount.toFixed(2)}`;
}

function publish(): void {
  form.post("/x/operations/pricing", { preserveScroll: true });
}

function selectPricingFile(event: Event): void {
  uploadForm.pricing_file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function uploadPricing(): void {
  uploadForm.post("/x/operations/pricing", {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => window.location.reload(),
  });
}
</script>

<template>
  <Head title="Operations Pricing" />

  <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100 sm:px-8">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
      <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-2">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Payment Operations</p>
          <h1 class="text-3xl font-semibold tracking-tight">Beta pricing and costing</h1>
          <p class="max-w-2xl text-sm text-slate-300">Update customer prices and expected provider costs for the beta. Changes publish and become effective immediately.</p>
        </div>
        <a href="/x/operations" class="text-sm font-medium text-amber-300 hover:text-amber-200">Back to operations</a>
      </header>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
          <div>
            <h2 class="font-semibold">Customer pricing</h2>
            <p class="text-xs text-slate-400">Active version {{ props.pricing.active_version }} · {{ props.pricing.currency }}</p>
          </div>
          <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">Beta write mode</span>
        </div>

        <div class="flex flex-col gap-4 border-b border-slate-800 bg-slate-950/40 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="font-semibold">Spreadsheet update</h3>
            <p class="mt-1 max-w-2xl text-xs text-slate-400">Download the current pricing and provider-cost list, edit the columns, then upload it to publish every row as one new active version.</p>
          </div>
          <div class="flex flex-col gap-2 sm:items-end">
            <a href="/x/operations/pricing/template" class="text-sm font-semibold text-amber-300 hover:text-amber-200">Download current pricing template</a>
            <form class="flex flex-wrap items-center justify-end gap-2" @submit.prevent="uploadPricing">
              <input type="file" accept=".csv,.txt,text/csv" class="block max-w-full text-xs text-slate-300 file:mr-2 file:rounded-md file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-100 hover:file:bg-slate-600" @change="selectPricingFile" />
              <button type="submit" :disabled="!uploadForm.pricing_file || uploadForm.processing" class="rounded-lg border border-amber-400/60 px-3 py-2 text-xs font-semibold text-amber-300 transition hover:bg-amber-400/10 disabled:cursor-not-allowed disabled:opacity-40">
                {{ uploadForm.processing ? "Uploading…" : "Upload and publish" }}
              </button>
            </form>
            <p v-if="uploadForm.errors.pricing_file" class="text-xs text-rose-300">{{ uploadForm.errors.pricing_file }}</p>
          </div>
        </div>

        <form class="flex flex-col gap-5 p-5" @submit.prevent="publish">
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2 rounded-xl border border-amber-400/20 bg-amber-400/5 p-4">
              <h3 class="font-semibold text-amber-200">Provider costing</h3>
              <p class="mt-1 text-xs text-slate-400">Expected cost paid to providers or incurred by the platform. The basis is recorded with the active revision.</p>
            </div>
            <label v-for="(item, index) in props.pricing.items" :key="item.reference" class="flex flex-col gap-2 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
              <span class="text-sm font-medium">{{ item.label }}</span>
              <span class="text-xs text-slate-500">{{ item.reference }}</span>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <span class="text-xs text-slate-400">Customer price</span>
                  <div class="flex items-center gap-2">
                    <span class="text-slate-400">₱</span>
                    <input v-model="form.items[index].unit_price" inputmode="decimal" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none ring-amber-400 focus:ring-2" />
                  </div>
                </div>
                <div>
                  <span class="text-xs text-slate-400">Expected provider cost</span>
                  <div class="flex items-center gap-2">
                    <span class="text-slate-400">₱</span>
                    <input v-model="form.items[index].expected_provider_cost" inputmode="decimal" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none ring-amber-400 focus:ring-2" />
                  </div>
                </div>
              </div>
              <input v-model="form.items[index].expected_provider_cost_basis" aria-label="Cost basis" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-slate-300 outline-none ring-amber-400 focus:ring-2" placeholder="Cost basis" />
              <span :class="(Number.parseFloat(form.items[index].unit_price) || 0) - (Number.parseFloat(form.items[index].expected_provider_cost) || 0) < 0 ? 'text-rose-300' : 'text-emerald-300'" class="text-xs">
                Expected spread: {{ formatAmount((Number.parseFloat(form.items[index].unit_price) || 0) - (Number.parseFloat(form.items[index].expected_provider_cost) || 0)) }}
                <span v-if="(Number.parseFloat(form.items[index].unit_price) || 0) - (Number.parseFloat(form.items[index].expected_provider_cost) || 0) < 0">· below expected cost</span>
              </span>
            </label>
          </div>

          <div class="grid gap-3 border-t border-slate-800 pt-5 sm:grid-cols-3">
            <div><span class="text-xs text-slate-400">Customer prices</span><p class="text-lg font-semibold">{{ formatAmount(totals.customerPrice) }}</p></div>
            <div><span class="text-xs text-slate-400">Expected provider costs</span><p class="text-lg font-semibold">{{ formatAmount(totals.providerCost) }}</p></div>
            <div :class="totals.spread < 0 ? 'text-rose-300' : 'text-emerald-300'"><span class="text-xs">Expected spread</span><p class="text-lg font-semibold">{{ formatAmount(totals.spread) }}</p></div>
          </div>
          <p v-if="totals.spread < 0" class="rounded-lg border border-rose-400/30 bg-rose-400/10 px-3 py-2 text-xs text-rose-200">One or more items are priced below their expected provider cost. Publication is allowed for beta review.</p>

          <div class="flex flex-col gap-3 border-t border-slate-800 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-400">Every change is recorded with the operations session and activated immediately.</p>
            <button type="submit" :disabled="!changed || form.processing" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-40">
              {{ form.processing ? "Publishing…" : "Publish pricing" }}
            </button>
          </div>
          <p v-if="form.errors.items" class="text-sm text-rose-300">{{ form.errors.items }}</p>
        </form>
      </section>
    </div>
  </main>
</template>
