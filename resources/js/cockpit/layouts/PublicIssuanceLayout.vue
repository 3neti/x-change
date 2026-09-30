<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
</script>

<template>
  <Head title="Create a Pay Code" />
  <main
    class="min-h-svh bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
  >
    <div class="mx-auto w-full max-w-[86rem] px-3 py-5 sm:px-6 lg:px-8">
      <header
        class="mb-5 text-center"
        data-testid="public-auto-generate-header"
      >
        <p
          class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300"
        >
          Secure Pay Code issuance
        </p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
          Create and fund a Pay Code
        </h1>
        <p
          class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
        >
          Configure the Pay Code, review the exact total, then pay once. The Pay
          Code is issued only after payment is verified.
        </p>
      </header>
      <slot />
    </div>
  </main>
</template>
