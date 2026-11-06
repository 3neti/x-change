<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

type Payout = {
  id: number;
  code: string;
  amount: string;
  currency: string;
  destination: string | null;
  status: string;
  last_checked_at: string | null;
  retry_url: string | null;
};
type Case = {
  id?: number;
  reference?: string;
  code?: string;
  reason_code?: string;
  status?: string;
  job_type?: string;
  failure_type?: string;
  created_at?: string;
  opened_at?: string;
  failed_at?: string;
  amount_minor?: number;
  currency?: string;
  provider_transaction_reference?: string | null;
  provider_status?: string | null;
  last_checked_at?: string | null;
  resolution_url?: string | null;
  credit_url?: string | null;
  apply_url?: string | null;
  split_url?: string | null;
  candidates?: {
    id: number;
    reference: string;
    code: string;
    expected_amount_minor: number;
    currency: string;
    split_collection_minor?: number | null;
    split_excess_minor?: number | null;
  }[];
};
type Report = {
  reference: string;
  code: string;
  created_at: string;
  review_url: string;
  evidence: {
    transaction_reference: string;
    amount_minor: number;
    paid_at: string;
    contact?: string | null;
  };
};
defineOptions({ layout: [] });
const props = defineProps<{
  transactionReviews: Case[];
  fundingCases: Case[];
  reports: Report[];
  failures: Case[];
  payouts: Payout[];
}>();
const form = useForm({ reason: "", destination_ready: false });
const selectedPayout = ref<number | null>(null);
const selectedReport = ref<string | null>(null);
const reportForm = useForm({ note: "" });
const selectedReview = ref<number | null>(null);
const reviewForm = useForm({ reason: "", accounted_elsewhere: false });
const decisionForm = useForm({
  reason: "",
  confirmed: false,
  attempt_id: "",
  expected_collection_minor: 0,
  expected_excess_minor: 0,
});
const decision = ref<"credit" | "apply" | "split" | null>(null);
const selectedCandidate = computed(() =>
  props.transactionReviews
    .find((item) => item.id === selectedReview.value)
    ?.candidates?.find(
      (candidate) => String(candidate.id) === decisionForm.attempt_id,
    ),
);
const decisionError = computed(
  () => (decisionForm.errors as Record<string, string>).recovery,
);
const recoveryError = computed(
  () => (form.errors as Record<string, string>).recovery,
);
function selectPayout(id: number) {
  form.reset();
  form.clearErrors();
  selectedPayout.value = id;
}
function retry(url: string) {
  form.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      selectedPayout.value = null;
    },
  });
}
function selectReport(reference: string) {
  reportForm.reset();
  reportForm.clearErrors();
  selectedReport.value = reference;
}
function reviewReport(url: string) {
  reportForm.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      reportForm.reset();
      selectedReport.value = null;
    },
  });
}
function selectReview(id: number) {
  reviewForm.reset();
  reviewForm.clearErrors();
  selectedReview.value = id;
  decisionForm.reset();
  decisionForm.clearErrors();
  decision.value = null;
}
function submitDecision(url: string | null | undefined) {
  if (!url) return;
  if (decision.value === "split") {
    if (
      !selectedCandidate.value?.split_collection_minor ||
      !selectedCandidate.value?.split_excess_minor
    )
      return;
    decisionForm.expected_collection_minor =
      selectedCandidate.value.split_collection_minor;
    decisionForm.expected_excess_minor =
      selectedCandidate.value.split_excess_minor;
  }
  decisionForm.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      decisionForm.reset();
      selectedReview.value = null;
      decision.value = null;
    },
  });
}
function markAccounted(url: string) {
  reviewForm.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      reviewForm.reset();
      selectedReview.value = null;
    },
  });
}
</script>

<template>
  <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100 sm:px-8">
    <Head title="Payment recovery" />
    <div class="mx-auto flex max-w-5xl flex-col gap-8">
      <header>
        <p class="text-sm font-medium text-cyan-300">Operations</p>
        <h1 class="mt-2 text-3xl font-semibold">Payment recovery</h1>
        <p class="mt-2 text-slate-300">
          Review payments and transfers that need attention.
        </p>
      </header>
      <section class="grid gap-4 md:grid-cols-2">
        <div
          v-for="group in [
            { title: 'Payment reviews', cases: transactionReviews },
            { title: 'Funding cases', cases: fundingCases },
            { title: 'Recent job failures', cases: failures },
          ]"
          :key="group.title"
          class="rounded-2xl border border-slate-700 bg-slate-900 p-5"
        >
          <h2 class="text-lg font-semibold">{{ group.title }}</h2>
          <p v-if="!group.cases.length" class="mt-3 text-sm text-slate-400">
            No cases to show.
          </p>
          <ul v-else class="mt-4 divide-y divide-slate-700">
            <li
              v-for="item in group.cases"
              :key="item.reference ?? item.id"
              class="flex flex-col gap-1 py-3 text-sm"
            >
              <span class="font-medium">{{
                item.code ?? item.reference ?? `Case ${item.id}`
              }}</span>
              <span class="text-slate-300"
                >{{ item.reason_code ?? item.job_type ?? "Payment reported" }}
                <template v-if="item.amount_minor"
                  >· {{ item.currency }}
                  {{ (item.amount_minor / 100).toFixed(2) }}</template
                ></span
              >
              <span class="text-slate-400">{{
                item.created_at ?? item.opened_at ?? item.failed_at
              }}</span>
              <span
                v-if="item.provider_transaction_reference"
                class="break-all text-slate-400"
                >Provider reference:
                {{ item.provider_transaction_reference }}</span
              >
              <span v-if="item.provider_status" class="text-slate-400"
                >Provider status: {{ item.provider_status }}</span
              >
              <span v-if="item.last_checked_at" class="text-slate-400"
                >Last checked: {{ item.last_checked_at }}</span
              >
              <button
                v-if="
                  item.id && item.resolution_url && selectedReview !== item.id
                "
                type="button"
                class="w-fit text-cyan-300"
                @click="selectReview(item.id)"
              >
                Record an outcome
              </button>
              <div
                v-if="item.id && selectedReview === item.id"
                class="flex flex-wrap gap-3 py-2"
              >
                <button
                  v-if="item.candidates?.length"
                  type="button"
                  class="text-cyan-300"
                  @click="decision = 'apply'"
                >
                  Apply to a payment attempt
                </button>
                <button
                  v-if="item.candidates?.length && item.split_url"
                  type="button"
                  class="text-cyan-300"
                  @click="decision = 'split'"
                >
                  Collect and credit excess
                </button>
                <button
                  v-if="item.credit_url"
                  type="button"
                  class="text-cyan-300"
                  @click="decision = 'credit'"
                >
                  Credit excess to Client Funds
                </button>
                <button
                  type="button"
                  class="text-cyan-300"
                  @click="decision = null"
                >
                  Accounted elsewhere
                </button>
              </div>
              <form
                v-if="
                  item.id &&
                  selectedReview === item.id &&
                  decision &&
                  (decision === 'credit'
                    ? item.credit_url
                    : decision === 'split'
                      ? item.split_url
                      : item.apply_url)
                "
                class="flex flex-col gap-3 py-2"
                @submit.prevent="
                  submitDecision(
                    decision === 'credit'
                      ? item.credit_url
                      : decision === 'split'
                        ? item.split_url
                        : item.apply_url,
                  )
                "
              >
                <p class="text-slate-300">
                  The provider transaction will be checked again before this
                  decision is applied.
                </p>
                <p v-if="decision === 'split'" class="text-slate-300">
                  The amount still due will be collected, and the remainder
                  credited to the issuer’s Client Funds.
                </p>
                <label v-if="decision !== 'credit'"
                  >Payment attempt
                  <select
                    v-model="decisionForm.attempt_id"
                    required
                    class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2"
                  >
                    <option value="" disabled>Select an attempt</option>
                    <option
                      v-for="candidate in item.candidates"
                      :key="candidate.id"
                      :value="String(candidate.id)"
                    >
                      {{ candidate.code }} · {{ candidate.reference }} ·
                      {{ candidate.currency }}
                      {{ (candidate.expected_amount_minor / 100).toFixed(2) }}
                    </option>
                  </select>
                </label>
                <p
                  v-if="
                    decision === 'split' &&
                    selectedCandidate?.split_collection_minor &&
                    selectedCandidate?.split_excess_minor
                  "
                  class="text-slate-300"
                >
                  Collect {{ selectedCandidate.currency }}
                  {{
                    (selectedCandidate.split_collection_minor / 100).toFixed(2)
                  }}
                  · Credit Client Funds {{ selectedCandidate.currency }}
                  {{ (selectedCandidate.split_excess_minor / 100).toFixed(2) }}
                </p>
                <p
                  v-else-if="decision === 'split' && selectedCandidate"
                  class="text-rose-300"
                >
                  This attempt has no eligible split to confirm.
                </p>
                <label
                  >Evidence and reason<textarea
                    v-model="decisionForm.reason"
                    required
                    minlength="8"
                    maxlength="2000"
                    class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2"
                  />
                </label>
                <label class="flex items-center gap-2"
                  ><input
                    v-model="decisionForm.confirmed"
                    type="checkbox"
                    required
                  />
                  I confirmed the evidence and this decision.</label
                >
                <p v-if="decisionError" class="text-rose-300">
                  {{ decisionError }}
                </p>
                <button
                  type="submit"
                  :disabled="
                    decisionForm.processing ||
                    (decision === 'split' &&
                      (!selectedCandidate?.split_collection_minor ||
                        !selectedCandidate?.split_excess_minor))
                  "
                  class="w-fit rounded-lg bg-cyan-300 px-4 py-2 font-medium text-slate-950 disabled:opacity-50"
                >
                  {{
                    decision === "credit"
                      ? "Credit Client Funds"
                      : decision === "split"
                        ? "Collect and credit excess"
                        : "Apply payment"
                  }}
                </button>
              </form>
              <form
                v-if="
                  item.id &&
                  item.resolution_url &&
                  selectedReview === item.id &&
                  !decision
                "
                class="flex flex-col gap-3 py-2"
                @submit.prevent="markAccounted(item.resolution_url)"
              >
                <p class="text-slate-300">
                  Use this only after confirming the payment is accounted for
                  elsewhere. This action does not transfer money.
                </p>
                <label
                  >Evidence and reason
                  <textarea
                    v-model="reviewForm.reason"
                    required
                    minlength="8"
                    maxlength="2000"
                    class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2"
                  />
                </label>
                <label class="flex items-center gap-2"
                  ><input
                    v-model="reviewForm.accounted_elsewhere"
                    type="checkbox"
                    required
                  />
                  I confirmed this payment is accounted for elsewhere.</label
                >
                <button
                  type="submit"
                  :disabled="reviewForm.processing"
                  class="w-fit rounded-lg border border-cyan-300 px-3 py-2 text-cyan-300 disabled:opacity-50"
                >
                  Record outcome
                </button>
              </form>
            </li>
          </ul>
        </div>
      </section>
      <section class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">Missing payment reports</h2>
        <p v-if="!reports.length" class="mt-3 text-sm text-slate-400">
          No reports to show.
        </p>
        <div
          v-for="report in reports"
          :key="report.reference"
          class="mt-4 rounded-xl border border-slate-700 p-4"
        >
          <p class="font-semibold">
            {{ report.code }} · {{ report.reference }}
          </p>
          <button
            v-if="selectedReport !== report.reference"
            type="button"
            class="mt-3 text-sm text-cyan-300"
            @click="selectReport(report.reference)"
          >
            Review report
          </button>
          <div v-else class="mt-4 flex flex-col gap-3 text-sm">
            <p>
              Transaction reference: {{ report.evidence.transaction_reference }}
            </p>
            <p>
              Amount: ₱{{ (report.evidence.amount_minor / 100).toFixed(2) }}
            </p>
            <p>Paid at: {{ report.evidence.paid_at }}</p>
            <p v-if="report.evidence.contact">
              Contact: {{ report.evidence.contact }}
            </p>
            <p class="text-slate-400">
              Recording a review does not apply a payment or transfer funds.
            </p>
            <form
              class="flex flex-col gap-3"
              @submit.prevent="reviewReport(report.review_url)"
            >
              <label
                >Review note
                <textarea
                  v-model="reportForm.note"
                  required
                  minlength="8"
                  maxlength="2000"
                  class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2"
                />
              </label>
              <button
                type="submit"
                :disabled="reportForm.processing"
                class="w-fit rounded-lg bg-cyan-300 px-4 py-2 font-medium text-slate-950 disabled:opacity-50"
              >
                Record review
              </button>
            </form>
          </div>
        </div>
      </section>
      <section class="rounded-2xl border border-slate-700 bg-slate-900 p-5">
        <h2 class="text-lg font-semibold">Transfer recovery</h2>
        <p v-if="!payouts.length" class="mt-3 text-sm text-slate-400">
          No transfers need attention.
        </p>
        <div
          v-for="payout in payouts"
          :key="payout.id"
          class="mt-4 rounded-xl border border-slate-700 p-4"
        >
          <p class="font-semibold">
            {{ payout.code }} · {{ payout.currency }} {{ payout.amount }}
          </p>
          <p class="mt-1 text-sm text-slate-300">
            {{ payout.status }} ·
            {{ payout.destination ?? "Destination unavailable" }}
          </p>
          <p class="mt-1 text-xs text-slate-400">
            Last checked: {{ payout.last_checked_at ?? "Not yet checked" }}
          </p>
          <button
            v-if="payout.retry_url && selectedPayout !== payout.id"
            type="button"
            class="mt-4 rounded-lg border border-cyan-300 px-4 py-2 text-sm text-cyan-300"
            @click="selectPayout(payout.id)"
          >
            Review retry
          </button>
          <form
            v-if="payout.retry_url && selectedPayout === payout.id"
            class="mt-4 flex flex-col gap-3"
            @submit.prevent="retry(payout.retry_url)"
          >
            <label class="text-sm"
              >Reason for retry
              <input
                v-model="form.reason"
                required
                minlength="8"
                maxlength="500"
                class="mt-1 block w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2"
            /></label>
            <label class="flex items-center gap-2 text-sm"
              ><input
                v-model="form.destination_ready"
                type="checkbox"
                required
              />
              The original destination can receive the transfer.</label
            >
            <p v-if="recoveryError" class="text-sm text-rose-300">
              {{ recoveryError }}
            </p>
            <button
              type="submit"
              :disabled="form.processing"
              class="w-fit rounded-lg bg-cyan-300 px-4 py-2 font-medium text-slate-950 disabled:opacity-50"
            >
              Retry transfer
            </button>
          </form>
        </div>
      </section>
    </div>
  </main>
</template>
