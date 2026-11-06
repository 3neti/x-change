<script setup lang="ts">
import { ref } from "vue";
import { index } from "@/routes/x-change/cockpit/pay-codes/value-flow-payouts";

const props = defineProps<{ code: string }>();
type Payment = {
  number: number;
  amount_minor: number;
  currency: string;
  received_at: string | null;
  delivery_status: string;
  destination: string | null;
  retry_url: string | null;
};
const payments = ref<Payment[]>([]);
const loaded = ref(false);
const busy = ref(false);
const error = ref("");
const ready = ref<Record<number, boolean>>({});
const checkUrl = ref<string | null>(null);
const message = ref("");

async function load(): Promise<void> {
  busy.value = true;
  error.value = "";
  try {
    const response = await fetch(index({ code: props.code }).url, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error();
    const payload = await response.json();
    payments.value = payload.payments;
    checkUrl.value = payload.check_url ?? null;
    loaded.value = true;
  } catch {
    error.value = "Payment details are unavailable. Try again.";
  } finally {
    busy.value = false;
  }
}

async function checkStatus(): Promise<void> {
  if (!checkUrl.value || busy.value) return;
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    const csrf =
      document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";
    const xsrf = document.cookie
      .split("; ")
      .find((part) => part.startsWith("XSRF-TOKEN="))
      ?.slice("XSRF-TOKEN=".length);
    const response = await fetch(checkUrl.value, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
        ...(xsrf ? { "X-XSRF-TOKEN": decodeURIComponent(xsrf) } : {}),
      },
    });
    if (!response.ok) throw new Error();
    message.value = "Status check requested. Reload shortly to see the result.";
  } catch {
    error.value = "The status check could not be requested. Try again.";
  } finally {
    busy.value = false;
  }
}

async function retry(payment: Payment): Promise<void> {
  if (!payment.retry_url || !ready.value[payment.number] || busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    const csrf =
      document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";
    const xsrf = document.cookie
      .split("; ")
      .find((part) => part.startsWith("XSRF-TOKEN="))
      ?.slice("XSRF-TOKEN=".length);
    const response = await fetch(payment.retry_url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
        ...(xsrf ? { "X-XSRF-TOKEN": decodeURIComponent(xsrf) } : {}),
      },
      body: JSON.stringify({ destination_ready: true }),
    });
    if (!response.ok) throw new Error();
    ready.value[payment.number] = false;
  } catch {
    error.value =
      "The payout could not be retried. Check its status or contact support.";
  } finally {
    busy.value = false;
  }
  if (!error.value) await load();
}
</script>

<template>
  <section
    class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
    data-testid="value-flow-recovery"
  >
    <div class="flex items-center justify-between gap-3">
      <h4 class="font-semibold">Payments and transfers</h4>
      <button
        type="button"
        class="text-sm underline"
        :disabled="busy"
        @click="load"
      >
        {{ loaded ? "Reload" : "View payments" }}
      </button>
    </div>
    <button
      v-if="loaded && checkUrl"
      type="button"
      class="mt-3 rounded-lg border px-3 py-2 text-sm"
      :disabled="busy"
      @click="checkStatus"
    >
      Check payment and transfer status
    </button>
    <p v-if="message" role="status" class="mt-2 text-sm">{{ message }}</p>
    <p v-if="error" role="alert" class="mt-2 text-sm">{{ error }}</p>
    <p
      v-else-if="loaded && !payments.length"
      class="mt-2 text-sm text-slate-500"
    >
      No payments recorded.
    </p>
    <article
      v-for="payment in payments"
      :key="payment.number"
      class="mt-3 space-y-2 rounded-xl border p-3 text-sm dark:border-slate-800"
    >
      <p class="font-semibold">
        Payment {{ payment.number }} · {{ payment.currency }}
        {{ (payment.amount_minor / 100).toFixed(2) }}
      </p>
      <p>
        Payment received<span v-if="payment.received_at">
          · {{ new Date(payment.received_at).toLocaleString() }}</span
        >
      </p>
      <p>
        {{
          payment.delivery_status === "delivered"
            ? "Transfer delivered"
            : payment.delivery_status === "pending"
              ? "Transfer processing"
              : payment.delivery_status === "review"
                ? "Transfer needs review"
                : "Collected to Client Funds"
        }}<span v-if="payment.destination"> · {{ payment.destination }}</span>
      </p>
      <template v-if="payment.retry_url"
        ><label class="flex gap-2"
          ><input v-model="ready[payment.number]" type="checkbox" /> The
          original receiving account is ready to accept transfers.</label
        ><button
          type="button"
          class="rounded-lg bg-slate-900 px-3 py-2 text-white disabled:opacity-50 dark:bg-white dark:text-slate-900"
          :disabled="busy || !ready[payment.number]"
          @click="retry(payment)"
        >
          Retry transfer
        </button></template
      >
    </article>
  </section>
</template>
