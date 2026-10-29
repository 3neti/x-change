<script setup lang="ts">
import { computed, ref } from "vue";
import { useClipboard } from "@/composables/useClipboard";
import type { CockpitOnDemandIssuanceFundingProjection as Projection } from "../types";
const props = defineProps<{
  projection: Projection;
  delayed: boolean;
  error: string | null;
}>();
defineEmits<{ view: [] }>();
const notice = ref("");
const clipboard = useClipboard();
const ready = computed(() => props.projection.status === "issued");
const paid = computed(() =>
  ["funded", "issuing"].includes(props.projection.status),
);
const title = computed(() =>
  ready.value
    ? "Your Pay Code is ready"
    : paid.value
      ? "Payment received"
      : props.projection.status === "issuance_attention"
        ? "Issuance needs attention"
        : ["expired", "cancelled"].includes(props.projection.status)
          ? "Order closed"
          : ["underfunded", "payment_ambiguous"].includes(
                props.projection.status,
              )
            ? "Payment needs review"
            : "Checking your payment…",
);
const amount = computed(() =>
  new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: props.projection.order.currency,
  }).format(
    props.projection.pricing?.pay_code_value ??
      props.projection.order.voucher?.amount ??
      props.projection.order.required_amount_minor / 100,
  ),
);
async function copy(): Promise<void> {
  const copied = await clipboard.copy(
    props.projection.order.voucher?.code ?? "",
  );
  notice.value = copied
    ? "Pay Code copied."
    : "Open the Pay Code to copy its details.";
}
async function share(): Promise<void> {
  const voucher = props.projection.order.voucher;
  if (!voucher) return;
  try {
    if (navigator.share)
      await navigator.share({
        title: `Pay Code ${voucher.code}`,
        url: voucher.claim_url,
      });
    else {
      const copied = await clipboard.copy(voucher.claim_url);
      notice.value = copied
        ? "Claim link copied."
        : "Open the Pay Code to share its details.";
    }
  } catch (error) {
    if (!(error instanceof Error && error.name === "AbortError"))
      notice.value = "Open the Pay Code to share its details.";
  }
}
</script>

<template>
  <section
    class="mt-4 grid gap-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-slate-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-slate-100"
    data-testid="issuance-progress-card"
  >
    <div role="status" aria-live="polite" aria-atomic="true">
      <p class="font-semibold">{{ title }}</p>
      <p v-if="paid" class="mt-1 text-sm">
        {{
          delayed
            ? "Your payment is confirmed. We’re still preparing your Pay Code."
            : `Preparing your ${amount} Pay Code…`
        }}
      </p>
      <p v-else-if="!ready" class="mt-1 text-sm">
        {{ projection.lifecycle.message }}
      </p>
      <p v-if="error" class="mt-1 text-sm">{{ error }}</p>
    </div>
    <p class="break-all text-xs text-slate-600 dark:text-slate-300">
      {{ amount }} · Order {{ projection.order.reference }}
    </p>
    <div class="flex flex-wrap gap-2">
      <button
        type="button"
        class="min-h-10 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800"
        @click="$emit('view')"
      >
        {{ ready ? "View Pay Code" : "View status" }}
      </button>
      <template v-if="ready && projection.order.voucher">
        <button
          type="button"
          class="min-h-10 rounded-lg border border-emerald-600 px-4 font-semibold"
          @click="copy"
        >
          Copy code
        </button>
        <button
          type="button"
          class="min-h-10 rounded-lg border border-emerald-600 px-4 font-semibold"
          @click="share"
        >
          Share
        </button>
      </template>
      <a
        v-if="ready && projection.batch?.detail_url"
        :href="projection.batch.detail_url"
        class="inline-flex min-h-10 items-center px-4 font-semibold underline"
        >View batch</a
      >
    </div>
    <p v-if="notice" role="status" class="text-sm">{{ notice }}</p>
  </section>
</template>
