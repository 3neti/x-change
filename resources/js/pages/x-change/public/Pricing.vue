<script setup lang="ts">
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import {
  ArrowRight,
  BadgeCheck,
  Calculator,
  CircleAlert,
  Info,
  ReceiptText,
  ShieldCheck,
} from "lucide-vue-next";
import PublicNavigation from "../../../cockpit/components/PublicNavigation.vue";

defineOptions({ layout: null });

type Navigation = {
  pricing_url: string | null;
  claim_url: string | null;
  create_url: string | null;
  login_url: string | null;
  register_url: string | null;
};

type PriceItem = {
  code: string;
  name: string;
  amount_minor: number;
};

type PriceGroup = {
  key: string;
  label: string;
  description: string;
  items: PriceItem[];
};

type PriceExample = {
  key: string;
  label: string;
  description: string;
  principal_minor: number;
  service_fees_minor: number;
  amount_due_minor: number;
};

type Pricing = {
  available: boolean;
  currency: string;
  groups: PriceGroup[];
  billing: {
    mode: string;
    customer_charging_active: boolean;
    heading: string;
    message: string;
  };
  examples: PriceExample[];
  provenance: {
    catalog_reference: string | null;
    catalog_version: number | null;
    offering_reference: string | null;
    offering_version: number | null;
  } | null;
  unavailable_message?: string;
};

const props = defineProps<{
  pricing: Pricing;
  public_navigation: Navigation;
}>();

const money = computed(
  () =>
    new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: props.pricing.currency || "PHP",
      minimumFractionDigits: 2,
    }),
);

const provenance = computed(() => {
  if (!props.pricing.provenance) {
    return null;
  }

  const catalog = props.pricing.provenance.catalog_version
    ? `Catalog v${props.pricing.provenance.catalog_version}`
    : null;
  const offering = props.pricing.provenance.offering_version
    ? `Offering v${props.pricing.provenance.offering_version}`
    : null;

  return ["Current active price list", catalog, offering]
    .filter(Boolean)
    .join(" · ");
});

function formatMinor(amountMinor: number): string {
  return money.value.format(amountMinor / 100);
}
</script>

<template>
  <Head title="Pay Code pricing" />
  <main
    class="min-h-svh overflow-hidden bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
    data-testid="public-pricing-page"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[34rem] bg-[radial-gradient(circle_at_15%_15%,rgba(16,185,129,0.16),transparent_38%),radial-gradient(circle_at_85%_0%,rgba(14,165,233,0.12),transparent_34%)]"
      aria-hidden="true"
    />

    <div class="relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
      <PublicNavigation :navigation="public_navigation" active="pricing" />

      <header class="mx-auto max-w-3xl py-12 text-center sm:py-16">
        <p
          class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.18em] text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200"
        >
          <ReceiptText class="size-3.5" aria-hidden="true" />
          Transparent pricing
        </p>
        <h1
          class="mt-5 text-4xl font-black tracking-[-0.04em] sm:text-5xl lg:text-6xl"
        >
          What will my Pay Code cost?
        </h1>
        <p
          class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300"
        >
          Start with the value you want to send, then add only the instructions
          you need. You will always see the exact amount due before paying.
        </p>

        <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
          <Link
            v-if="public_navigation.create_url"
            :href="public_navigation.create_url"
            class="inline-flex min-h-11 items-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"
            data-testid="public-pricing-create"
          >
            Create a Pay Code
            <ArrowRight class="size-4" aria-hidden="true" />
          </Link>
          <a
            href="#price-list"
            class="inline-flex min-h-11 items-center rounded-full px-5 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white"
          >
            See the price list
          </a>
        </div>
      </header>

      <section
        class="mb-8 rounded-3xl border p-5 sm:p-6"
        :class="
          pricing.billing.customer_charging_active
            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40'
            : 'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/40'
        "
        data-testid="public-pricing-billing-status"
      >
        <div class="flex items-start gap-3">
          <BadgeCheck
            v-if="pricing.billing.customer_charging_active"
            class="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300"
            aria-hidden="true"
          />
          <Info
            v-else
            class="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300"
            aria-hidden="true"
          />
          <div>
            <h2 class="font-bold">{{ pricing.billing.heading }}</h2>
            <p
              class="mt-1 max-w-4xl text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
              {{ pricing.billing.message }}
            </p>
          </div>
        </div>
      </section>

      <section
        v-if="!pricing.available"
        class="rounded-3xl border border-amber-200 bg-amber-50 p-7 dark:border-amber-900 dark:bg-amber-950/40"
        data-testid="public-pricing-unavailable"
      >
        <CircleAlert
          class="size-6 text-amber-700 dark:text-amber-300"
          aria-hidden="true"
        />
        <h2 class="mt-4 text-xl font-black">Pricing temporarily unavailable</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
          {{ pricing.unavailable_message }}
        </p>
      </section>

      <template v-else>
        <section
          id="price-list"
          aria-labelledby="price-list-heading"
          data-testid="public-price-list"
        >
          <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
          >
            <div>
              <p
                class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
              >
                Current prices
              </p>
              <h2
                id="price-list-heading"
                class="mt-2 text-3xl font-black tracking-tight"
              >
                Pay only for the instructions you add.
              </h2>
            </div>
            <p
              v-if="provenance"
              class="text-xs font-semibold text-slate-500 dark:text-slate-400"
              data-testid="public-pricing-provenance"
            >
              {{ provenance }}
            </p>
          </div>

          <div class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="group in pricing.groups"
              :key="group.key"
              class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              :data-testid="`public-pricing-group-${group.key}`"
            >
              <h3 class="text-lg font-black tracking-tight">
                {{ group.label }}
              </h3>
              <p
                class="mt-1 min-h-10 text-sm leading-5 text-slate-500 dark:text-slate-400"
              >
                {{ group.description }}
              </p>
              <dl class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                <div
                  v-for="item in group.items"
                  :key="item.code"
                  class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                >
                  <dt class="text-sm text-slate-700 dark:text-slate-200">
                    {{ item.name }}
                  </dt>
                  <dd class="shrink-0 text-sm font-black tabular-nums">
                    {{ formatMinor(item.amount_minor) }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>
        </section>

        <section class="py-14" aria-labelledby="pricing-examples-heading">
          <div class="max-w-3xl">
            <p
              class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
            >
              Cost examples
            </p>
            <h2
              id="pricing-examples-heading"
              class="mt-2 text-3xl font-black tracking-tight"
            >
              Know what is value, what is a fee, and what is due now.
            </h2>
            <p
              class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
              These examples are calculated by the same pricing engine used by
              the Pay Code composer.
            </p>
          </div>

          <div class="mt-7 grid gap-4 lg:grid-cols-3">
            <article
              v-for="example in pricing.examples"
              :key="example.key"
              class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
              :data-testid="`public-pricing-example-${example.key}`"
            >
              <Calculator class="size-5 text-emerald-600" aria-hidden="true" />
              <h3 class="mt-4 font-black">{{ example.label }}</h3>
              <p
                class="mt-1 min-h-12 text-sm leading-6 text-slate-500 dark:text-slate-400"
              >
                {{ example.description }}
              </p>
              <dl
                class="mt-5 space-y-3 border-t border-slate-100 pt-5 text-sm dark:border-slate-800"
              >
                <div class="flex justify-between gap-4">
                  <dt class="text-slate-500 dark:text-slate-400">
                    Pay Code value
                  </dt>
                  <dd class="font-bold tabular-nums">
                    {{ formatMinor(example.principal_minor) }}
                  </dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-slate-500 dark:text-slate-400">
                    Listed service fees
                  </dt>
                  <dd class="font-bold tabular-nums">
                    {{ formatMinor(example.service_fees_minor) }}
                  </dd>
                </div>
                <div
                  class="flex justify-between gap-4 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                  <dt class="font-black">Amount due now</dt>
                  <dd
                    class="text-lg font-black tabular-nums text-emerald-700 dark:text-emerald-300"
                  >
                    {{ formatMinor(example.amount_due_minor) }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>
        </section>
      </template>

      <section
        class="mb-10 flex flex-col gap-6 rounded-3xl bg-slate-950 p-7 text-white sm:flex-row sm:items-center sm:justify-between dark:bg-white dark:text-slate-950"
      >
        <div class="max-w-2xl">
          <ShieldCheck
            class="size-6 text-emerald-400 dark:text-emerald-600"
            aria-hidden="true"
          />
          <h2 class="mt-4 text-2xl font-black tracking-tight">
            Review the exact total before paying.
          </h2>
          <p class="mt-2 text-sm leading-6 text-slate-300 dark:text-slate-600">
            The price list explains the parts. The composer gives the
            authoritative total for your exact Pay Code.
          </p>
        </div>
        <Link
          v-if="public_navigation.create_url"
          :href="public_navigation.create_url"
          class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 text-sm font-bold text-white transition hover:bg-emerald-700"
        >
          Try it now
          <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
      </section>

      <footer
        class="flex items-center justify-between gap-4 border-t border-slate-200 py-7 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
      >
        <span>The active commercial offering is the source of truth.</span>
        <span class="hidden sm:inline">Powered by x-change</span>
      </footer>
    </div>
  </main>
</template>
