<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import {
  AlertTriangle,
  ArrowLeft,
  ArrowUpRight,
  CheckCircle2,
  CircleDashed,
} from "lucide-vue-next";
import { index as campaignsIndex } from "@/routes/x-change/cockpit/campaigns";
import { show as payCodeShow } from "@/routes/x-change/cockpit/pay-codes";
import type { CampaignPolicyLifecycle } from "../campaignPolicyLifecycle";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type { CockpitHeaderPageProps } from "../types";
import { formatAbsoluteTime } from "../utils/dateTime";

type Props = CockpitHeaderPageProps & {
  lifecycles: CampaignPolicyLifecycle[];
  campaign_filter?: {
    reference: string;
    title: string;
    endpoint_slug: string;
  } | null;
};
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
  return value ? formatAbsoluteTime(value) : "Not available";
}

function stageIcon(item: CampaignPolicyLifecycle): typeof AlertTriangle {
  if (item.attention_required) return AlertTriangle;
  if (item.stage === "policy_succeeded") return CheckCircle2;
  return CircleDashed;
}

function isDemoSummaryReady(item: CampaignPolicyLifecycle): boolean {
  return (
    item.stage === "policy_succeeded" &&
    item.policy?.status === "succeeded" &&
    item.policy?.outcome_status === "succeeded" &&
    item.policy?.result_code === "policy_issued_demo"
  );
}

function stageGuidance(item: CampaignPolicyLifecycle): string {
  if (isDemoSummaryReady(item)) {
    return "A demonstration summary was recorded. This is not an issued insurance policy.";
  }
  const guidance: Record<string, string> = {
    provisional_coverage_active:
      "Payment is recognized and provisional coverage is active.",
    awaiting_completion_claim:
      "The completion Pay Code is ready for the remaining evidence.",
    claim_evidence_ready:
      "Claim evidence is complete and ready for governance review.",
    policy_awaiting_approval:
      "Policy completion is waiting for checker approval.",
    policy_authorized:
      "Policy completion is authorized and awaiting its recorded outcome.",
    policy_succeeded: "Policy completion has been recorded successfully.",
    policy_failed: "Policy completion failed and needs operator review.",
    policy_indeterminate:
      "The policy outcome is uncertain and needs operator review.",
  };

  return guidance[item.stage] ?? "Lifecycle progress is available for review.";
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
            v-if="props.campaign_filter"
            data-testid="campaign-policy-lifecycle-filter"
            class="mt-2 break-words text-sm font-semibold"
          >
            {{ props.campaign_filter.title }} ·
            {{ props.campaign_filter.endpoint_slug }}
          </p>
          <p
            class="mt-1 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
          >
            Read-only progress from recognized payment through provisional
            coverage, completion evidence, governance, and policy outcome.
          </p>
          <span
            class="mt-3 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
          >
            Read-only
          </span>
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
              <h2
                class="break-words text-base font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ item.campaign.name ?? "Campaign policy lifecycle" }}
              </h2>
              <p
                class="mt-1 break-all text-xs text-slate-500 dark:text-slate-400"
              >
                {{ item.campaign.reference }} · Revision
                {{ item.campaign.revision_id ?? "not available" }}
              </p>
            </div>
            <div class="flex min-w-0 flex-wrap items-center gap-2">
              <span
                class="inline-flex max-w-full items-center gap-1.5 whitespace-normal break-words rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-200"
                data-testid="campaign-policy-lifecycle-stage"
              >
                <component
                  :is="stageIcon(item)"
                  class="size-3.5 shrink-0"
                  aria-hidden="true"
                />
                {{
                  isDemoSummaryReady(item)
                    ? "Demo summary ready"
                    : (stageLabels[item.stage] ?? label(item.stage))
                }}
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

          <p
            class="border-b border-slate-200 px-4 py-3 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:text-slate-300"
            data-testid="campaign-policy-lifecycle-guidance"
          >
            {{ stageGuidance(item) }}
          </p>

          <dl
            class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4 dark:bg-slate-800"
          >
            <div class="min-w-0 bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
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
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.payment.settled_at
                    ? `Settled ${time(item.payment.settled_at)}`
                    : "Settlement pending"
                }}
              </dd>
            </div>
            <div class="min-w-0 bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              >
                Coverage
              </dt>
              <dd
                class="mt-2 break-words text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ label(item.coverage.type) }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{ label(item.coverage.status) }} ·
                {{ money(item.coverage.amount_minor, item.coverage.currency) }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.coverage.effective_at
                    ? `Effective ${time(item.coverage.effective_at)}`
                    : "Effective time unavailable"
                }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.coverage.expires_at
                    ? `Expires ${time(item.coverage.expires_at)}`
                    : "No expiry recorded"
                }}
              </dd>
            </div>
            <div class="min-w-0 bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              >
                Completion
              </dt>
              <dd
                class="mt-2 break-words text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ item.completion?.pay_code ?? "Not issued" }}
              </dd>
              <dd v-if="item.completion?.pay_code" class="mt-2">
                <Link
                  :href="payCodeShow(item.completion.pay_code)"
                  class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                  :aria-label="`View completion Pay Code ${item.completion.pay_code}`"
                  data-testid="campaign-policy-lifecycle-completion-link"
                >
                  <span class="truncate">View completion Pay Code</span>
                  <ArrowUpRight class="size-3.5 shrink-0" aria-hidden="true" />
                </Link>
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.completion?.claim_number
                    ? `Claim ${item.completion.claim_number}`
                    : "Claim not completed"
                }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.completion?.claim_completed_at
                    ? `Completed ${time(item.completion.claim_completed_at)}`
                    : "Completion time unavailable"
                }}
              </dd>
            </div>
            <div class="min-w-0 bg-white p-4 dark:bg-slate-900">
              <dt
                class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              >
                Governance
              </dt>
              <dd
                class="mt-2 break-words text-sm font-semibold text-slate-950 dark:text-slate-50"
              >
                {{ label(item.policy?.status ?? null) }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.policy
                    ? `Outcome: ${label(item.policy.outcome_status)}`
                    : "No policy request"
                }}
              </dd>
              <dd
                v-if="item.policy?.result_code"
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
                data-testid="campaign-policy-lifecycle-result-code"
              >
                Result: {{ label(item.policy.result_code) }}
              </dd>
              <dd
                class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"
              >
                {{
                  item.updated_at
                    ? `Updated ${time(item.updated_at)}`
                    : "Update time unavailable"
                }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
    </section>
  </CockpitLayout>
</template>
