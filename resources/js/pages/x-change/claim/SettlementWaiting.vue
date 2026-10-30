<script setup lang="ts">
import { Head } from "@inertiajs/vue3";

defineProps<{ code: string; reviewed: boolean; paid: boolean }>();
</script>

<template>
  <main class="min-h-screen bg-slate-950 px-5 py-12 text-slate-100">
    <Head title="Settlement status" />
    <section
      class="mx-auto max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-8"
    >
      <p class="text-sm font-semibold uppercase tracking-widest text-sky-300">
        Pay Code {{ code }}
      </p>
      <h1 class="mt-4 text-3xl font-semibold">
        Your information has been recorded
      </h1>
      <p v-if="paid" class="mt-5 text-lg text-slate-300">
        PhilHealth payment has been confirmed.
      </p>
      <p v-else-if="reviewed" class="mt-5 text-lg text-slate-300">
        The hospital has reviewed the details. Settlement is being prepared.
      </p>
      <p v-else class="mt-5 text-lg text-slate-300">
        Waiting for the hospital to review the details.
      </p>
    </section>
  </main>
</template>
