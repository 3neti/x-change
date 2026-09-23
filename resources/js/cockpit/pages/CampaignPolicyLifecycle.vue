<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  CircleDashed,
  ShieldCheck,
} from "lucide-vue-next";
import { index as campaignsIndex } from "@/routes/x-change/cockpit/campaigns";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type { CockpitHeaderPageProps } from "../types";
import { formatAbsoluteTime } from "../utils/dateTime";

type SafeLifecycle = {
  schema: "x-change.campaign-policy-lifecycle.v1";
  stage: string;
  attention_required: boolean;
  campaign: {
    reference: string | null;
    name: string | null;
    revision_id: string | null;
  };
  payment: {
    recognition_reference: string | null;
    gross_amount_minor: number | null;
    currency: string | null;
    settled_at: string | null;
  };
  coverage: {
    reference: string;
    type: string;
    status: string;
    amount_minor: number | null;
    currency: string;
    effective_at: string | null;
    expires_at: string | null;
  };
  completion: {
    issuance_reference: string;
    pay_code: string | null;
    issued_at: string | null;
    claim_number: number | null;
    claim_completed_at: string | null;
    projection_reference: string | null;
    projected_at: string | null;
  } | null;
  policy: {
    request_reference: string;
    status: string;
    requested_at: string | null;
    approved_at: string | null;
    outcome_reference: string | null;
    outcome_status: string | null;
    result_code: string | null;
    recorded_at: string | null;
  } | null;
  updated_at: string | null;
};

type Props = CockpitHeaderPageProps & { lifecycles: SafeLifecycle[] };
const props = defineProps<Props>();

const stageLabels: Record<string, string> = {
  provisional_coverage_active: "Provisional coverage active",
  awaiting_completion_claim: "Awaiting completion claim",
  claim_evidence_ready: "Claim evidence ready",
  policy_awaiting_approval: "Awaiting checker approval",
  policy_authorized: "Policy completion authorized",
  policy_succeeded: "Policy completed",
  policy_failed: "Policy completion failed",
  policy_indeterminate: "Policy outcome indeterminate",
};

function label(value: string | null): string {
  if (!value) return "Not available";
  return value
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function money(minor: number | null, currency: string | null): string {
  if (minor === null) return "Not available";
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: currency ?? "PHP",
  }).format(minor / 100);
}

function time(value: string | null): string {
  return value ? formatAbsoluteTime(value) : "Not yet";
}
</script>

<template>
  <CockpitLayout
    active-navigation="campaigns"
    :cockpit-header-read-model="props.cockpit_header_read_model"
    :cockpit-entry-notice="props.cockpit_entry_notice"
    mobile-presentation="edge"
  >
    <section
      class="mx-auto grid w-full max-w-6xl gap-5 p-4 sm:p-6"
      data-testid="campaign-policy-lifecycle-page"
    >
      <header
        class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="min-w-0">
          <p
            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300"
          >
            Campaign settlement
          </p>
          <h1
            class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50"
          >
            Policy lifecycle
          </h1>
          <p
            class="mt-1 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
          >
            Read-only progress from recognized payment through provisional
            coverage, completion evidence, governance, and policy outcome.
          </p>
        </div>
        <Link
          :href="campaignsIndex()"
          class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          data-testid="campaign-policy-lifecycle-back"
        >
          <ArrowLeft class="size-4" aria-hidden="true" />Back to campaigns
        </Link>
      </header>

      <div
        v-if="props.lifecycles.length === 0"
        class="grid min-h-64 place-items-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"
        data-testid="campaign-policy-lifecycle-empty"
      >
        <div>
          <CircleDashed
            class="mx-auto size-9 text-slate-400"
            aria-hidden="true"
          />
          <h2 class="mt-3 font-semibold text-slate-950 dark:text-slate-50">
            No policy lifecycles yet
          </h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            A lifecycle appears after a qualifying campaign payment creates
            provisional coverage.
          </p>
        </div>
      </div>

      <div
        v-else
        class="grid gap-4"
        role="list"
        aria-label="Campaign policy lifecycles"
        data-testid="campaign-policy-lifecycle-list"
      >
        <article
          v-for="item in props.lifecycles"
          :key="item.coverage.reference"
          role="listitem"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
          :data-testid="`campaign-policy-lifecycle-${item.coverage.reference}`"
        >
          <div
            class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between dark:border-slate-800"
          >
            <div class="min-w-0">
              <p
                class="break-words text-base font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ item.campaign.name ?? "Campaign policy lifecycle" }}
              </p>
              <p
                class="mt-1 break-all text-xs text-slate-500 dark:text-slate-400"
              >
                {{ item.campaign.reference }} · Revision
                {{ item.campaign.revision_id ?? "not available" }}
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-200"
              >
                <CheckCircle2
                  v-if="item.stage === 'policy_succeeded'"
                  class="size-3.5"
                  aria-hidden="true"
                />
                <ShieldCheck v-else class="size-3.5" aria-hidden="true" />
                {{ stageLabels[item.stage] ?? label(item.stage) }}
              </span>
              <span
                v-if="item.attention_required"
                class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                data-testid="campaign-policy-lifecycle-attention"
              >
                <AlertTriangle class="size-3.5" aria-hidden="true" />Needs
                attention
              </span>
            </div>
          </div>

          <dl
            class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4 dark:bg-slate-800"
          >
            <div class="bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
              >
                Payment
              </dt>
              <dd
                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{
                  money(item.payment.gross_amount_minor, item.payment.currency)
                }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Settled {{ time(item.payment.settled_at) }}
              </dd>
            </div>
            <div class="bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
              >
                Coverage
              </dt>
              <dd
                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ label(item.coverage.type) }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Effective {{ time(item.coverage.effective_at) }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Expires {{ time(item.coverage.expires_at) }}
              </dd>
            </div>
            <div class="bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
              >
                Completion
              </dt>
              <dd
                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ item.completion?.pay_code ?? "Not issued" }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Claim {{ item.completion?.claim_number ?? "not completed" }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                {{ time(item.completion?.claim_completed_at ?? null) }}
              </dd>
            </div>
            <div class="bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
              >
                Governance
              </dt>
              <dd
                class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ label(item.policy?.status ?? null) }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Outcome: {{ label(item.policy?.outcome_status ?? null) }}
              </dd>
              <dd class="mt-1 text-xs text-slate-500">
                Updated {{ time(item.updated_at) }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </section>
  </CockpitLayout>
</template>
