<script setup lang="ts">
import {
  LoaderCircle,
  RefreshCw,
  X,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import type {
  CockpitOnDemandIssuanceFundingProjection,
  CockpitPrimaryFundingWorkspaceMode,
} from "../types";
import type { PayCodeStampPreviewState } from "../payCodeStampPreview";
import CockpitPayCodeCanvas from "./CockpitPayCodeCanvas.vue";
import CockpitFundingMethodSelector from "./CockpitFundingMethodSelector.vue";

const props = defineProps<{
  open: boolean;
  projection: CockpitOnDemandIssuanceFundingProjection | null;
  stampPreview?: PayCodeStampPreviewState | null;
}>();

const emit = defineEmits<{
  issued: [projection: CockpitOnDemandIssuanceFundingProjection];
  cancelled: [];
  closed: [projection: CockpitOnDemandIssuanceFundingProjection];
}>();

const current = ref<CockpitOnDemandIssuanceFundingProjection | null>(
  props.projection,
);
const showingStamp = ref(false);
const stampBackground = ref("");
watch(() => [props.open, props.projection?.order.reference], () => { showingStamp.value = false; });
const selectedMode = ref<CockpitPrimaryFundingWorkspaceMode>("bank_transfer");
const checking = ref(false);
const monitoring = ref(false);
const refreshing = ref(false);
const retrying = ref(false);
const cancelling = ref(false);
const selectingFundingMethod = ref(false);
const bankMobile = ref("");
const error = ref<string | null>(null);
const monitorMessage = ref<string | null>(null);
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let emittedIssuedReference: string | null = null;

async function downloadBatch(): Promise<void> {
  const batch = current.value?.batch;
  if (batch?.download_url === undefined) return;

  const token = sessionStorage.getItem(`x-change.batch-token:${batch.reference}`);
  if (token === null) return;

  const response = await fetch(batch.download_url, {
    credentials: "same-origin",
    headers: { Accept: "text/csv", "X-Issuance-Batch-Token": token },
  });
  if (!response.ok) return;

  const blob = await response.blob();
  const href = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = href;
  anchor.download = `${batch.reference}.csv`;
  anchor.click();
  URL.revokeObjectURL(href);
}

function guestAccessHeader(): Record<string, string> {
  const token = current.value?.guest_access_token?.trim();

  return token ? { "X-XChange-Guest-Order-Token": token } : {};
}

const terminal = computed(() =>
  ["issued", "cancelled", "expired"].includes(current.value?.status ?? ""),
);
const needsAttention = computed(
  () => current.value?.status === "issuance_attention",
);
const paymentReceived = computed(
  () => current.value?.order.receipt.verified_at !== null,
);
const paymentNeedsReview = computed(() =>
  ["underfunded", "payment_ambiguous"].includes(current.value?.status ?? ""),
);
const paymentOpen = computed(() =>
  ["awaiting_payment", "payer_acknowledged", "verifying"].includes(
    current.value?.status ?? "",
  ),
);
const latePaymentCredited = computed(
  () =>
    current.value?.status === "expired" &&
    current.value.order.late_payment_disposition === "client_funds",
);
const batchCount = computed(() => current.value?.batch?.item_count ?? 0);
const isBatch = computed(() => batchCount.value > 0);
const issuanceLabel = computed(() =>
  `${Math.max(batchCount.value, 1)} Pay Code${batchCount.value > 1 ? "s" : ""}`,
);
const amount = computed(() => {
  const order = current.value?.order;

  if (order === undefined) {
    return "";
  }

  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: order.currency,
  }).format(order.expected_payment_minor / 100);
});
const instructions = computed<Record<string, unknown>>(
  () => current.value?.funding_selector.bank_transfer.instructions ?? {},
);
const fixedQrPh = computed(() => current.value?.funding_selector.qr_ph ?? null);
const dialogTitle = computed(() => {
  if (current.value?.status === "expired") {
    return latePaymentCredited.value
      ? "Payment added to Client Funds"
      : "This order expired";
  }

  if (current.value?.status === "cancelled") {
    return "Funding cancelled";
  }

  if (needsAttention.value) {
    return paymentReceived.value
      ? "Payment received — issuance needs attention"
      : "Issuance needs attention";
  }

  if (paymentNeedsReview.value) {
    return "Payment needs review";
  }

  if (["funded", "issuing"].includes(current.value?.status ?? "")) {
    return "Payment received";
  }

  return `Pay ${amount.value}`;
});
const dialogDescription = computed(() => {
  if (needsAttention.value) {
    return paymentReceived.value
      ? "Your payment was verified and remains protected. Pay Code issuance could not complete. Do not pay again."
      : "Payment instructions could not be prepared. No payment was requested. You can cancel safely.";
  }

  if (paymentNeedsReview.value) {
    return "We found a payment, but it cannot be applied automatically. Do not pay again.";
  }

  if (terminal.value) {
    return current.value?.lifecycle.message ?? "";
  }

  if (["funded", "issuing"].includes(current.value?.status ?? "")) {
    return `Your payment is verified. Keep this window open while we finish issuing ${issuanceLabel.value.toLowerCase()}.`;
  }

  return "Choose Bank Transfer or QR Ph, pay the exact amount once, and keep this window open. We will check the payment automatically.";
});
const lastCheckedLabel = computed(() => {
  const checkedAt = current.value?.monitor.last_checked_at;

  return checkedAt === null || checkedAt === undefined
    ? "Waiting for the first automatic check"
    : `Last checked ${new Date(checkedAt).toLocaleTimeString([], { hour: "numeric", minute: "2-digit", second: "2-digit" })}`;
});

function rememberBatchToken(projection: CockpitOnDemandIssuanceFundingProjection | null): void {
  const batch = projection?.batch;
  const token = projection?.guest_access_token?.trim();

  if (batch && token) {
    sessionStorage.setItem(`x-change.batch-token:${batch.reference}`, token);
  }
}

watch(
  () => props.projection,
  (projection) => {
    current.value = projection;
    rememberBatchToken(projection);
    selectedMode.value =
      projection?.funding_selector.default_mode ?? "bank_transfer";
    monitorMessage.value = null;
    schedulePoll();
  },
  { immediate: true },
);

watch(
  () => props.open,
  () => schedulePoll(),
);

watch(selectedMode, (mode) => {
  showingStamp.value = false;
  if (mode === "self_top_up" && current.value?.actions.funding_method
    && current.value.checkout?.selected_method !== "qr_ph") {
    void selectFundingMethod("qr_ph");
  }
});

onBeforeUnmount(clearPoll);

function csrfHeader(): Record<string, string> {
  const token = document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute("content");

  return token ? { "X-CSRF-TOKEN": token } : {};
}

function clearPoll(): void {
  if (pollTimer !== null) {
    clearTimeout(pollTimer);
    pollTimer = null;
  }
}

async function selectFundingMethod(method: "qr_ph" | "bank_transfer"): Promise<void> {
  const action = current.value?.actions.funding_method;

  if (!action || selectingFundingMethod.value) {
    return;
  }

  selectingFundingMethod.value = true;
  error.value = null;

  try {
    const response = await fetch(action, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...csrfHeader(),
        ...guestAccessHeader(),
      },
      body: JSON.stringify({ method, mobile: method === "bank_transfer" ? bankMobile.value : undefined }),
    });

    if (!response.ok) {
      error.value = response.status === 422
        ? "Enter a valid Philippine mobile number to view bank transfer instructions."
        : "The funding method could not be updated. Refresh and try again.";
      return;
    }

    current.value = (await response.json()) as CockpitOnDemandIssuanceFundingProjection;
  } catch {
    error.value = "The funding method could not be updated. Refresh and try again.";
  } finally {
    selectingFundingMethod.value = false;
  }
}

function schedulePoll(): void {
  clearPoll();

  if (
    !props.open ||
    current.value === null ||
    terminal.value ||
    !current.value.monitor.enabled ||
    !current.value.monitor.eligible
  ) {
    return;
  }

  const delay = Math.max(5000, current.value.monitor.interval_milliseconds);
  pollTimer = setTimeout(() => void refresh(), delay);
}

async function refresh(): Promise<void> {
  if (current.value === null || checking.value || refreshing.value) {
    schedulePoll();

    return;
  }

  refreshing.value = true;
  try {
    const response = await fetch(current.value.actions.show, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...guestAccessHeader(),
      },
    });

    if (response.ok) {
      const next =
        (await response.json()) as CockpitOnDemandIssuanceFundingProjection;
      current.value = next;
      error.value = null;

      if (
        current.value.status === "issued" &&
        emittedIssuedReference !== current.value.order.reference
      ) {
        emittedIssuedReference = current.value.order.reference;
        emit("issued", current.value);
      }

      if (current.value.monitor.enabled && current.value.monitor.eligible) {
        await requestVerification(true);
      }
    } else {
      error.value =
        "Status could not be refreshed. Try again shortly. Do not pay again.";
    }
  } catch {
    error.value =
      "Status could not be refreshed. Try again shortly. Do not pay again.";
  } finally {
    refreshing.value = false;
    schedulePoll();
  }
}

async function retryIssuance(): Promise<void> {
  if (
    current.value === null ||
    !current.value.retry.eligible ||
    retrying.value
  ) {
    return;
  }

  retrying.value = true;
  error.value = null;

  try {
    const response = await fetch(current.value.actions.retry_issuance, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...csrfHeader(),
        ...guestAccessHeader(),
      },
    });

    if (!response.ok) {
      throw new Error(
        "Retry is unavailable. Refresh the order status or contact support. Do not pay again.",
      );
    }

    current.value =
      (await response.json()) as CockpitOnDemandIssuanceFundingProjection;
    monitorMessage.value = "Issuance retry requested. Do not pay again.";
  } catch (exception) {
    error.value =
      exception instanceof Error
        ? exception.message
        : "Retry could not be requested.";
  } finally {
    retrying.value = false;
  }
}

function closeForNow(): void {
  clearPoll();
  if (current.value !== null) {
    emit("closed", current.value);
  }
}

async function requestVerification(automatic = false): Promise<void> {
  if (
    current.value === null ||
    checking.value ||
    monitoring.value ||
    !current.value.monitor.eligible
  ) {
    return;
  }

  if (automatic) {
    monitoring.value = true;
    monitorMessage.value = "Checking for your payment…";
  } else {
    checking.value = true;
    error.value = null;
  }

  try {
    const response = await fetch(current.value.actions.verify, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...csrfHeader(),
        ...guestAccessHeader(),
      },
    });
    const body =
      (await response.json()) as CockpitOnDemandIssuanceFundingProjection & {
        message?: string;
      };

    if (!response.ok) {
      throw new Error(
        body.message ?? "The payment check could not be started.",
      );
    }

    current.value = body;
    monitorMessage.value = automatic
      ? "Automatic checking is active. Please pay only once."
      : "Payment check started. You do not need to pay again.";
  } catch (exception) {
    if (automatic) {
      monitorMessage.value =
        "Automatic checking will retry shortly. You do not need to pay again.";
    } else {
      error.value =
        exception instanceof Error
          ? exception.message
          : "The payment check failed.";
    }
  } finally {
    if (automatic) {
      monitoring.value = false;
    } else {
      checking.value = false;
    }
    schedulePoll();
  }
}

function checkNow(): void {
  void requestVerification(false);
}

async function cancel(): Promise<void> {
  if (
    current.value === null ||
    !current.value.order.can_cancel ||
    cancelling.value
  ) {
    return;
  }

  cancelling.value = true;
  error.value = null;

  try {
    const response = await fetch(current.value.actions.cancel, {
      method: "DELETE",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...csrfHeader(),
        ...guestAccessHeader(),
      },
    });
    const body =
      (await response.json()) as CockpitOnDemandIssuanceFundingProjection & {
        message?: string;
      };

    if (!response.ok) {
      throw new Error(
        body.message ?? "The funding order could not be cancelled.",
      );
    }

    current.value = body;
    emit("cancelled");
  } catch (exception) {
    error.value =
      exception instanceof Error ? exception.message : "Cancellation failed.";
  } finally {
    cancelling.value = false;
  }
}

function text(value: unknown): string | null {
  return typeof value === "string" && value.trim() !== "" ? value.trim() : null;
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && current"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/75 p-2 backdrop-blur-sm sm:p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="on-demand-funding-title"
      data-testid="on-demand-issuance-funding-dialog"
      @click.self.stop
      @keydown.esc.prevent.stop
    >
      <section
        class="max-h-[96dvh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl dark:bg-slate-950"
      >
        <header class="flex items-start justify-between gap-4">
          <div>
            <h2
              id="on-demand-funding-title"
              class="text-2xl font-bold text-slate-950 dark:text-white"
            >
              {{ dialogTitle }}
            </h2>
            <p class="mt-1 text-xs text-slate-500">{{ issuanceLabel }}</p>
            <p v-if="needsAttention || paymentNeedsReview" class="mt-2 text-sm text-amber-800 dark:text-amber-200">{{ dialogDescription }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="terminal"
              type="button"
              class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-900"
              aria-label="Close"
              @click="closeForNow"
            >
              <X class="size-5" aria-hidden="true" />
            </button>
          </div>
        </header>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs" role="status" aria-live="polite">
          <span>{{ current.lifecycle.steps.find(step => step.state === 'current')?.label ?? current.status }}</span>
          <span v-if="paymentOpen && current.monitor.enabled" class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300" data-testid="on-demand-automatic-verification">
            <RefreshCw :class="['size-3', monitoring ? 'animate-spin' : '']" aria-hidden="true" />
            Auto-checking · Pay once
          </span>
        </div>
        <div
          v-if="terminal"
          :class="[
            'mt-5 rounded-2xl border p-4 text-sm',
            latePaymentCredited
              ? 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100'
              : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
          ]"
          data-testid="on-demand-terminal-outcome"
        >
          <p class="font-bold">
            {{
              latePaymentCredited
                ? "Your money is safe"
                : "No further action on this order"
            }}
          </p>
          <p class="mt-1 leading-6">
            {{ current.lifecycle.message }}
          </p>
          <button
            v-if="current.batch?.download_url && current.batch.item_count > 1 && current.batch.issued_item_count > 0"
            class="mt-4 inline-flex min-h-10 items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700"
            type="button"
            @click="downloadBatch"
          >
            Download {{ current.batch.issued_item_count }} Pay Codes
          </button>
          <a
            v-if="current.batch?.detail_url && current.batch.issued_item_count > 0"
            :href="current.batch.detail_url"
            class="mt-4 inline-flex min-h-10 items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700"
          >
            View Pay Code
          </a>
          <a
            v-else-if="current.batch?.workspace_url && current.batch.issued_item_count > 0"
            :href="current.batch.workspace_url"
            class="mt-4 ml-2 inline-flex min-h-10 items-center justify-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-200"
          >
            Open Pay Codes
          </a>
          <p v-if="latePaymentCredited" class="mt-2 leading-6">
            Start a new issuance when you are ready. Under shortfall funding,
            your Client Funds will be applied first.
          </p>
        </div>

        <CockpitFundingMethodSelector
          v-if="paymentOpen && !needsAttention"
          v-model="selectedMode"
          :selector="current.funding_selector"
          compact
        />

        <div
          v-if="needsAttention"
          class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
          role="status"
        >
          <p class="font-bold">Your payment is protected</p>
          <p class="mt-1 leading-6">
            No Pay Code was issued. You can request a retry when it is
            available; otherwise an operator will review this order. Do not pay
            again.
          </p>
        </div>

        <div
          v-else-if="paymentNeedsReview"
          class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
          role="status"
          data-testid="on-demand-payment-review"
        >
          <p class="font-bold">Do not pay again</p>
          <p class="mt-1 leading-6">
            {{ current.lifecycle.message }}
          </p>
        </div>

        <section v-if="paymentOpen && !needsAttention" class="mt-3" aria-label="Pay Code payment card">
          <div class="mb-2 flex justify-end">
            <button v-if="stampPreview" type="button" class="min-h-8 rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-300 dark:hover:bg-slate-800" :aria-pressed="showingStamp" aria-controls="funding-flip-card" data-testid="funding-flip-toggle" @click="showingStamp = !showingStamp">
              {{ showingStamp ? 'Back to payment' : 'View stamp' }} ↻
            </button>
          </div>
          <div class="[perspective:1200px]">
            <div id="funding-flip-card" class="relative h-[280px] rounded-2xl transition-transform duration-500 [transform-style:preserve-3d] motion-reduce:transition-none" :class="showingStamp ? '[transform:rotateY(180deg)]' : ''" data-testid="funding-flip-card">
              <div class="absolute inset-0 overflow-auto rounded-2xl border border-slate-200 bg-slate-50 [backface-visibility:hidden] dark:border-slate-800 dark:bg-slate-900" :inert="showingStamp" :aria-hidden="showingStamp" data-testid="funding-payment-face">
                <img v-if="stampBackground" :src="stampBackground" alt="" aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-[0.06] blur-[2px]" data-testid="funding-stamp-background" />
                <div class="relative h-full">

        <div
          v-if="selectedMode === 'bank_transfer'"
          class="flex h-full flex-col justify-center p-5"
          data-testid="on-demand-bank-transfer-instructions"
        >
          <div v-if="current.actions.funding_method && !current.checkout?.bank_transfer_unlocked" class="w-full">
            <label for="checkout-bank-mobile" class="block text-sm font-semibold text-slate-900 dark:text-white">Your mobile number</label>
            <div class="mt-3 grid gap-3">
              <input id="checkout-bank-mobile" v-model="bankMobile" type="tel" inputmode="tel" autocomplete="tel" placeholder="09xxxxxxxxx" class="min-w-0 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
              <button type="button" :disabled="selectingFundingMethod" class="min-h-10 justify-self-end rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950" @click="selectFundingMethod('bank_transfer')">Show instructions</button>
            </div>
          </div>
          <template v-else>
          <dl class="grid gap-3 text-sm">
            <div
              v-if="text(instructions.institution)"
              class="flex justify-between gap-4"
            >
              <dt class="text-slate-500">Bank</dt>
              <dd class="font-semibold text-slate-900 dark:text-white">
                {{ text(instructions.institution) }}
              </dd>
            </div>
            <div
              v-if="text(instructions.account_name)"
              class="flex justify-between gap-4"
            >
              <dt class="text-slate-500">Account name</dt>
              <dd
                class="text-right font-semibold text-slate-900 dark:text-white"
              >
                {{ text(instructions.account_name) }}
              </dd>
            </div>
            <div
              v-if="text(instructions.funding_address)"
              class="flex justify-between gap-4"
            >
              <dt class="text-slate-500">Account number</dt>
              <dd
                class="font-mono font-semibold text-slate-900 dark:text-white"
              >
                {{ text(instructions.funding_address) }}
              </dd>
            </div>
          </dl>
          </template>
        </div>

        <div
          v-else-if="
            paymentOpen &&
            selectedMode === 'self_top_up' &&
            fixedQrPh?.fixed_amount &&
            fixedQrPh.image
          "
          class="relative h-full"
          data-testid="on-demand-fixed-qr-ph"
        >
          <span class="absolute right-4 top-2 text-base font-bold sm:text-lg tabular-nums text-slate-950 dark:text-white" data-testid="funding-qr-amount">{{ amount }}</span>
          <img :src="fixedQrPh.image" alt="Order-specific fixed-amount QR Ph" class="absolute bottom-3 left-1/2 size-[232px] -translate-x-1/2 bg-white object-contain sm:left-3 sm:size-64 sm:translate-x-0" width="256" height="256" data-testid="on-demand-qr-ph-artifact" />
        </div>

        <div
          v-else-if="paymentOpen"
          class="flex h-full items-center justify-center p-5 text-sm text-amber-900"
        >
          {{
            current.funding_selector.methods.find(
              (method) => method.workspace_mode === selectedMode,
            )?.unavailable_reason
          }}
        </div>

                </div>
              </div>
              <div class="absolute inset-0 flex items-center justify-center overflow-hidden rounded-2xl bg-slate-950 [backface-visibility:hidden] [transform:rotateY(180deg)]" :inert="!showingStamp" :aria-hidden="!showingStamp" data-testid="funding-stamp-face">
                <CockpitPayCodeCanvas v-if="stampPreview" v-bind="stampPreview" preview-only class="w-full" @preview-image="stampBackground = $event" />
              </div>
            </div>
          </div>
        </section>

        <p
          v-if="error"
          class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700"
          role="alert"
        >
          {{ error }}
        </p>

        <details class="mt-3 rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-800" data-testid="funding-order-details">
          <summary class="cursor-pointer font-semibold text-slate-500">Order details</summary>
          <div class="mt-2 grid gap-2">
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Order reference</span>
            <span
              class="font-mono font-semibold text-slate-900 dark:text-white"
              >{{ current.order.reference }}</span
            >
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-slate-500">Expires</span>
            <span class="font-semibold text-slate-900 dark:text-white">{{
              current.order.expires_at
                ? new Date(current.order.expires_at).toLocaleString()
                : "No expiry"
            }}</span>
          </div>
          <p v-if="current.pricing && current.pricing.customer_charge > 0" class="text-xs text-slate-500 dark:text-slate-400">
            {{ new Intl.NumberFormat("en-PH", { style: "currency", currency: current.pricing.currency }).format(current.pricing.pay_code_value) }} Pay Code value + {{ new Intl.NumberFormat("en-PH", { style: "currency", currency: current.pricing.currency }).format(current.pricing.customer_charge) }} issuance fee
          </p>
          <p
            class="border-t border-slate-200 pt-2 text-slate-600 dark:border-slate-800 dark:text-slate-300"
            role="status"
          >
            {{ current.lifecycle.message }}
          </p>
          <a
            v-if="current.public_links?.recovery"
            :href="current.public_links.recovery"
            class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
            data-testid="public-issuance-recovery-link"
          >
            Save / reopen order
          </a>
          <p v-if="monitorMessage" role="status">{{ monitorMessage }}</p>
          <p>{{ lastCheckedLabel }}</p>
          </div>
        </details>
        <footer
          v-if="current.status !== 'issued'"
          class="sticky bottom-0 mt-3 flex flex-wrap justify-end gap-2 bg-white pt-2 dark:bg-slate-950"
        >
          <button
            v-if="
              ['funded', 'issuing', 'issuance_attention'].includes(
                current.status,
              )
            "
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
            :disabled="refreshing"
            data-testid="on-demand-status-refresh"
            @click="refresh"
          >
            <LoaderCircle
              v-if="refreshing"
              class="size-4 animate-spin"
              aria-hidden="true"
            />
            <RefreshCw v-else class="size-4" aria-hidden="true" />
            Refresh status
          </button>
          <button
            v-if="needsAttention && current.retry.eligible"
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
            :disabled="retrying"
            data-testid="on-demand-issuance-retry"
            @click="retryIssuance"
          >
            <LoaderCircle
              v-if="retrying"
              class="size-4 animate-spin"
              aria-hidden="true"
            />
            Try issuing again
          </button>
          <p
            v-if="needsAttention && current.retry.pending"
            class="text-sm text-slate-600 dark:text-slate-300"
          >
            Retry requested. Refresh status shortly. Do not pay again.
          </p>
          <p
            v-else-if="needsAttention && current.retry.attempts_remaining === 0"
            class="text-sm text-slate-600 dark:text-slate-300"
          >
            This order needs operator review. Do not pay again.
          </p>
          <p
            v-else-if="
              needsAttention &&
              current.retry.next_available_at &&
              !current.retry.eligible
            "
            class="text-sm text-slate-600 dark:text-slate-300"
          >
            You can refresh status after
            {{
              new Date(current.retry.next_available_at).toLocaleTimeString()
            }}
            to check whether another retry is available.
          </p>
          <button
            v-if="paymentOpen && !needsAttention"
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
            :disabled="checking || monitoring"
            data-testid="on-demand-payment-check"
            @click="checkNow"
          >
            <LoaderCircle
              v-if="checking"
              class="size-4 animate-spin"
              aria-hidden="true"
            />
            <RefreshCw v-else class="size-4" aria-hidden="true" />
            Check now
          </button>
          <button
            v-if="terminal || paymentNeedsReview"
            type="button"
            class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 px-4 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200"
            @click="closeForNow"
          >
            Close
          </button>
          <button
            v-if="current.order.can_cancel"
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
            :disabled="cancelling"
            @click="cancel"
          >
            <X class="size-4" aria-hidden="true" />
            Cancel safely
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
