<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PayCodeLogo from "../../components/x-change/PayCodeLogo.vue";

withDefaults(
  defineProps<{
    navigation?: {
      pricing_url: string | null;
      claim_url: string | null;
      create_url: string | null;
      login_url: string | null;
      register_url: string | null;
    };
    active?: "create" | "pricing" | null;
  }>(),
  {
    navigation: () => ({
      pricing_url: null,
      claim_url: null,
      create_url: null,
      login_url: null,
      register_url: null,
    }),
    active: null,
  },
);
</script>

<template>
  <nav
    class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200/70 dark:border-slate-800"
    aria-label="Pay Code"
    data-testid="public-navigation"
  >
    <Link
      :href="navigation.create_url ?? navigation.claim_url ?? '#'"
      class="inline-flex items-center gap-2 text-sm font-black tracking-tight text-slate-950 dark:text-white"
      data-testid="public-navigation-brand"
    >
      <PayCodeLogo
        variant="mark"
        size="header"
        class-name="!h-9 !max-h-9 !max-w-9"
        data-testid="public-navigation-brand-mark"
      />
      <span>Pay Code</span>
    </Link>

    <div class="flex items-center gap-1 text-sm font-semibold sm:gap-2">
      <Link
        v-if="navigation.pricing_url"
        :href="navigation.pricing_url"
        class="hidden rounded-full px-3 py-2 transition sm:inline-flex"
        :class="
          active === 'pricing'
            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200'
            : 'text-slate-600 hover:bg-white hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white'
        "
        data-testid="public-navigation-pricing"
      >
        Pricing
      </Link>
      <Link
        v-if="navigation.claim_url"
        :href="navigation.claim_url"
        class="hidden rounded-full px-3 py-2 text-slate-600 transition hover:bg-white hover:text-slate-950 sm:inline-flex dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white"
      >
        Claim a Pay Code
      </Link>
      <Link
        v-if="active !== 'create' && navigation.create_url"
        :href="navigation.create_url"
        class="inline-flex rounded-full bg-emerald-600 px-4 py-2 text-white shadow-sm transition hover:bg-emerald-700"
        data-testid="public-navigation-create"
      >
        Create a Pay Code
      </Link>
      <Link
        v-else-if="navigation.login_url"
        :href="navigation.login_url"
        class="inline-flex rounded-full border border-slate-300 bg-white px-4 py-2 text-slate-700 shadow-sm transition hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
        data-testid="public-navigation-sign-in"
      >
        Sign in
      </Link>
    </div>
  </nav>
</template>
