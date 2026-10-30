<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { store } from "@/routes/x-change/claim/settlement-review";

const props = defineProps<{
  code: string;
  amountMinor: number;
  evidenceManifest: string;
  reviewed: boolean;
  paid: boolean;
  preview?: boolean;
  evidence: { requirement: string; summary: string | null; status: string }[];
}>();
const form = useForm({
  amount_minor: props.amountMinor,
  evidence_manifest: props.evidenceManifest,
});
const amount = new Intl.NumberFormat("en-PH", {
  style: "currency",
  currency: "PHP",
}).format(props.amountMinor / 100);
</script>

<template>
  <main class="min-h-screen bg-slate-950 px-5 py-12 text-slate-100">
    <Head title="Review settlement" />
    <section
      class="mx-auto max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-8"
    >
      <p class="text-sm font-semibold uppercase tracking-widest text-sky-300">
        Pay Code {{ code }}
      </p>
      <h1 class="mt-4 text-3xl font-semibold">Hospital review</h1>
      <p class="mt-5 text-slate-300">
        The patient’s information has been recorded. Confirm the amount for
        settlement.
      </p>
      <ul class="mt-5 space-y-2 text-sm text-slate-300">
        <li
          v-for="item in evidence"
          :key="item.requirement"
          class="rounded-lg border border-slate-700 p-3"
        >
          <span class="font-semibold">{{ item.requirement }}</span
          ><span v-if="item.summary"> · {{ item.summary }}</span>
        </li>
      </ul>
      <p class="mt-6 text-sm text-slate-400">Settlement amount</p>
      <p class="mt-1 text-4xl font-semibold">{{ amount }}</p>
      <p v-if="paid" role="status" class="mt-7 text-emerald-300">
        PhilHealth payment confirmed.
      </p>
      <p v-else-if="reviewed" role="status" class="mt-7 text-emerald-300">
        Amount reviewed. Settlement is being prepared.
      </p>
      <form
        v-else
        class="mt-7"
        @submit.prevent="!preview && form.post(store.url(code))"
      >
        <p v-if="form.errors.amount_minor" class="mb-3 text-rose-300">
          {{ form.errors.amount_minor }}
        </p>
        <p v-if="form.errors.evidence_manifest" class="mb-3 text-rose-300">
          {{ form.errors.evidence_manifest }}
        </p>
        <button
          type="submit"
          :disabled="preview || form.processing"
          class="w-full rounded-xl bg-sky-400 px-5 py-4 font-semibold text-slate-950 disabled:opacity-50"
        >
          {{ form.processing ? "Confirming…" : "Confirm amount" }}
        </button>
      </form>
    </section>
  </main>
</template>
