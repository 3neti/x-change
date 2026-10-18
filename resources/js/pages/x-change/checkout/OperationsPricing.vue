<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

type PricingItem = {
  reference: string;
  label: string;
  category: string;
  currency: string;
  unit_price_minor: number;
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
  })),
});

const changed = computed(() => form.isDirty);

function publish(): void {
  form.post("/x/operations/pricing", { preserveScroll: true });
}
</script>

<template>
  <Head title="Operations Pricing" />

  <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100 sm:px-8">
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
      <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-2">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Payment Operations</p>
          <h1 class="text-3xl font-semibold tracking-tight">Beta pricing</h1>
          <p class="max-w-2xl text-sm text-slate-300">Update instruction prices for the beta. Changes publish and become effective immediately.</p>
        </div>
        <a href="/x/operations" class="text-sm font-medium text-amber-300 hover:text-amber-200">Back to operations</a>
      </header>

      <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
          <div>
            <h2 class="font-semibold">Price list</h2>
            <p class="text-xs text-slate-400">Active version {{ props.pricing.active_version }} · {{ props.pricing.currency }}</p>
          </div>
          <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-300">Beta write mode</span>
        </div>

        <form class="flex flex-col gap-5 p-5" @submit.prevent="publish">
          <div class="grid gap-3 sm:grid-cols-2">
            <label v-for="(item, index) in props.pricing.items" :key="item.reference" class="flex flex-col gap-2 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
              <span class="text-sm font-medium">{{ item.label }}</span>
              <span class="text-xs text-slate-500">{{ item.reference }}</span>
              <div class="flex items-center gap-2">
                <span class="text-slate-400">₱</span>
                <input v-model="form.items[index].unit_price" inputmode="decimal" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none ring-amber-400 focus:ring-2" />
              </div>
            </label>
          </div>

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
