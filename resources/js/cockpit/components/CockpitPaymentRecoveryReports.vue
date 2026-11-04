<script setup lang="ts">
import { ref } from "vue";
import {
  index,
  review,
} from "@/routes/x-change/cockpit/pay-codes/payment-recovery-reports";

const props = defineProps<{ code: string }>();
type Report = {
  reference: string;
  status: string;
  evidence: {
    transaction_reference: string;
    amount_minor: number;
    paid_at: string;
    contact?: string | null;
  };
  created_at: string;
  reviewed_at?: string | null;
  reviewed_by?: string | null;
  review_note?: string | null;
};
const reports = ref<Report[]>([]);
const loaded = ref(false);
const busy = ref(false);
const error = ref("");
const notes = ref<Record<string, string>>({});

async function load(): Promise<void> {
  busy.value = true;
  error.value = "";
  try {
    const response = await fetch(index({ code: props.code }).url, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error("unavailable");
    reports.value = (await response.json()).reports;
    loaded.value = true;
  } catch {
    error.value = "Reports are unavailable. Try again.";
  } finally {
    busy.value = false;
  }
}

async function markReviewed(report: Report): Promise<void> {
  const note = notes.value[report.reference]?.trim();
  if (!note || busy.value) return;
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
    const response = await fetch(
      review({ code: props.code, reference: report.reference }).url,
      {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
          ...(xsrf ? { "X-XSRF-TOKEN": decodeURIComponent(xsrf) } : {}),
        },
        body: JSON.stringify({ note }),
      },
    );
    if (!response.ok) throw new Error("unavailable");
    report.status = "reviewed";
    report.review_note = note;
    delete notes.value[report.reference];
  } catch {
    error.value = "Review could not be saved. Try again.";
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section
    class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
    data-testid="payment-recovery-reports"
  >
    <div class="flex items-center justify-between gap-3">
      <h4 class="font-semibold">Payment reports</h4>
      <button
        type="button"
        class="text-sm underline"
        :disabled="busy"
        @click="load"
      >
        {{ loaded ? "Refresh" : "Load reports" }}
      </button>
    </div>
    <p class="mt-2 text-xs text-slate-500">
      Payer-submitted details are unverified. Check provider records before
      taking action.
    </p>
    <p v-if="error" role="alert" class="mt-2 text-sm">{{ error }}</p>
    <p v-else-if="loaded && reports.length === 0" class="mt-2 text-sm">
      No payment reports.
    </p>
    <article
      v-for="report in reports"
      :key="report.reference"
      class="mt-4 grid gap-2 rounded-lg border p-3 text-sm dark:border-slate-800"
    >
      <p>
        <strong>Reference:</strong> {{ report.evidence.transaction_reference }}
      </p>
      <p>
        <strong>Amount:</strong> PHP
        {{ (report.evidence.amount_minor / 100).toFixed(2) }}
      </p>
      <p><strong>Payment time:</strong> {{ report.evidence.paid_at }}</p>
      <p v-if="report.evidence.contact">
        <strong>Contact:</strong> {{ report.evidence.contact }}
      </p>
      <p><strong>Status:</strong> {{ report.status }}</p>
      <p v-if="report.review_note"><strong>Review:</strong> {{ report.review_note }}</p>
      <template v-if="report.status === 'open'"
        ><label class="grid gap-1"
          >Review note<textarea
            v-model="notes[report.reference]"
            maxlength="2000"
            class="rounded border bg-transparent p-2"
          /></label
        ><button
          type="button"
          class="justify-self-start rounded border px-3 py-2"
          :disabled="busy || !notes[report.reference]?.trim()"
          @click="markReviewed(report)"
        >
          Mark reviewed
        </button></template
      >
    </article>
  </section>
</template>
