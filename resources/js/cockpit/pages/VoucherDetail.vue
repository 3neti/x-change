<script setup lang="ts">
import { computed } from "vue";
import CockpitPayCodeRecordWorkspace from "../components/CockpitPayCodeRecordWorkspace.vue";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type {
  CockpitCampaignNavigationContext,
  CockpitReadModelRedactions,
  CockpitVoucherDetailPageProps,
} from "../types";

const props = defineProps<CockpitVoucherDetailPageProps>();

const voucher = computed(() => props.read_model?.voucher);
const summary = computed<Record<string, unknown>>(
  () => voucher.value?.summary ?? {},
);
const collection = computed<Record<string, unknown>>(
  () => voucher.value?.collection ?? {},
);
const code = computed(
  () =>
    stringValue(summary.value.code) ??
    voucher.value?.code ??
    props.read_model?.code ??
    props.context?.code ??
    "Not wired",
);
const status = computed(
  () =>
    stringValue(collection.value.consumer_status) ??
    stringValue(summary.value.display_status) ??
    stringValue(summary.value.status) ??
    voucher.value?.status ??
    "not_wired",
);
const redactions = computed<CockpitReadModelRedactions>(
  () => voucher.value?.redactions ?? { payloads: "not-loaded" },
);
const distributionLinks = computed<Record<string, unknown> | null>(() => {
  const links = objectValue(voucher.value?.distribution_links);

  return links?.read_only === true ? links : null;
});
const beneficiaryRedeemUrl = computed(() =>
  stringValue(distributionLinks.value?.redeem_url),
);
const beneficiaryRedeemPath = computed(() =>
  stringValue(distributionLinks.value?.redeem_path),
);
const beneficiaryClaimQr = computed(() =>
  stringValue(distributionLinks.value?.claim_qr),
);
const redemption = computed<Record<string, unknown> | null>(() =>
  objectValue(summary.value.redemption),
);
const distributionWorkspaceHref = computed(
  () => `/x/cockpit/pay-codes/${encodeURIComponent(code.value)}/distribution`,
);
const explorerHref = computed(() => "/x/cockpit/pay-codes");

const campaignNavigationContext =
  computed<CockpitCampaignNavigationContext | null>(() => {
    const context = props.campaign_navigation_context;

    if (!context?.authorized || context.read_only !== true) {
      return null;
    }

    const planningKey = stringValue(context.planning_key);
    const executionId = stringValue(context.execution_id);
    const destination = stringValue(context.destination);

    if (!planningKey || !executionId || destination !== "pay_code_detail") {
      return null;
    }

    return {
      schema:
        stringValue(context.schema) ??
        "x-change.cockpit.campaign-navigation.v1",
      status: stringValue(context.status) ?? "available",
      authorized: true,
      source: stringValue(context.source) ?? "campaign_cockpit",
      planning_key: planningKey,
      execution_id: executionId,
      campaign_id: stringValue(context.campaign_id),
      audience_id: stringValue(context.audience_id),
      recipient_id: stringValue(context.recipient_id),
      destination,
      read_only: true,
      mutation: {
        enabled: false,
        status: stringValue(objectValue(context.mutation)?.status) ?? "blocked",
        reason:
          stringValue(objectValue(context.mutation)?.reason) ??
          "campaign-navigation-read-only",
      },
      redactions: {
        payloads:
          stringValue(context.redactions?.payloads) ??
          "navigation-context-only",
      },
    };
  });
const campaignExplorerReturnHref = computed(() => {
  if (!campaignNavigationContext.value) {
    return null;
  }

  return `/x/cockpit/pay-codes?${campaignQueryString({
    activity_code: code.value,
    activity_source: "operator_issuance_activity",
  })}`;
});
const campaignDashboardReturnHref = computed(() =>
  campaignNavigationContext.value
    ? `/x/cockpit?${campaignQueryString()}`
    : null,
);
const campaignNavigationSourceLabel = computed(() =>
  campaignValueLabel(campaignNavigationContext.value?.source, {
    campaign_cockpit: "Campaign Cockpit",
    x_campaign_adapter: "Campaign package adapter",
  }),
);
const campaignNavigationDestinationLabel = computed(() =>
  campaignValueLabel(campaignNavigationContext.value?.destination, {
    pay_code_detail: "Pay Code Detail",
    distribution_workspace: "Distribution Workspace",
  }),
);
const campaignNavigationSafetyLabel = computed(() =>
  campaignValueLabel(campaignNavigationContext.value?.mutation?.reason, {
    "campaign-navigation-read-only": "Campaign navigation only",
  }),
);
const campaignNavigationPayloadLabel = computed(() =>
  campaignValueLabel(campaignNavigationContext.value?.redactions?.payloads, {
    "navigation-context-only": "Navigation context only",
  }),
);

function stringValue(value: unknown): string | null {
  if (typeof value === "string" && value.trim() !== "") {
    return value.trim();
  }

  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }

  return null;
}

function objectValue(value: unknown): Record<string, unknown> | null {
  return typeof value === "object" && value !== null && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null;
}

function campaignValueLabel(
  value: unknown,
  labels: Record<string, string>,
): string {
  const normalized = stringValue(value);

  return normalized ? (labels[normalized] ?? normalized) : "Not provided";
}

function campaignQueryString(
  extra: Record<string, string | null> = {},
): string {
  const context = campaignNavigationContext.value;
  const params = new URLSearchParams();

  if (!context) {
    return "";
  }

  setParam(params, "campaign_planning_key", context.planning_key);
  setParam(params, "campaign_execution_id", context.execution_id);
  setParam(params, "campaign_id", stringValue(context.campaign_id));
  setParam(params, "campaign_audience_id", stringValue(context.audience_id));
  setParam(params, "campaign_recipient_id", stringValue(context.recipient_id));
  setParam(params, "campaign_source", stringValue(context.source));

  for (const [key, value] of Object.entries(extra)) {
    setParam(params, key, value);
  }

  return params.toString();
}

function setParam(
  params: URLSearchParams,
  key: string,
  value: string | null | undefined,
): void {
  if (value && value.trim() !== "") {
    params.set(key, value.trim());
  }
}
</script>

<template>
  <CockpitLayout
    active-navigation="pay-codes"
    :cockpit-header-read-model="props.cockpit_header_read_model"
  >
    <section class="space-y-6" data-testid="cockpit-voucher-detail-shell">
      <div
        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-detail-header"
      >
        <div
          class="flex flex-col gap-3 lg:flex-row lg:items-center"
          data-testid="cockpit-voucher-detail-header-row"
        >
          <div class="min-w-0 lg:w-64 lg:shrink-0 xl:w-72">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
              >
                Pay Code inspection
              </p>
              <span
                class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
              >
                read-only
              </span>
            </div>
            <h2
              class="mt-1 text-lg font-semibold leading-6 text-slate-950 dark:text-slate-50"
            >
              Pay Code Detail
            </h2>
            <p
              class="mt-0.5 text-xs leading-4 text-slate-600 dark:text-slate-300"
            >
              Inspect lifecycle state, claim readiness, and connected evidence.
            </p>
          </div>
          <dl
            class="grid w-full gap-1.5 rounded-lg bg-slate-50 p-1.5 text-xs sm:grid-cols-3 lg:min-w-0 lg:flex-1 dark:bg-slate-950"
            data-testid="cockpit-voucher-detail-header-facts"
          >
            <div
              v-for="fact in [
                { label: 'Pay Code', value: code },
                { label: 'Lifecycle status', value: status },
                {
                  label: 'Payload policy',
                  value: redactions.payloads ?? 'not-loaded',
                },
              ]"
              :key="fact.label"
              class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
              data-testid="cockpit-voucher-detail-header-fact"
            >
              <dt
                class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              >
                {{ fact.label }}
              </dt>
              <dd
                class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50"
              >
                {{ fact.value }}
              </dd>
            </div>
          </dl>
        </div>
        <details
          class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-800"
          data-testid="cockpit-voucher-detail-boundary"
        >
          <summary
            class="cursor-pointer text-[0.7rem] font-semibold text-slate-500 dark:text-slate-400"
          >
            Read-only limits
          </summary>
          <p
            class="mt-2 max-w-4xl text-xs leading-5 text-slate-500 dark:text-slate-400"
          >
            Inspection only. This page can open or copy the claim URL, but it
            cannot change the Pay Code, send messages, call providers, or move
            money.
          </p>
        </details>
      </div>

      <section
        v-if="campaignNavigationContext"
        class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm sm:p-5 dark:border-indigo-900/70 dark:bg-indigo-950/40"
        data-testid="cockpit-voucher-detail-campaign-navigation-context"
      >
        <div
          class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
        >
          <div class="min-w-0">
            <p
              class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700 dark:text-indigo-300"
            >
              Campaign context
            </p>
            <h3
              class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
            >
              Opened from campaign activity
            </h3>
            <p
              class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
              This Pay Code is being inspected with campaign-recipient context
              preserved. These links only change the read-only Cockpit view;
              they do not update campaign state, issue Pay Codes, send feedback,
              call providers, or move money.
            </p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-2">
            <a
              v-if="campaignExplorerReturnHref"
              :href="campaignExplorerReturnHref"
              class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
              data-testid="cockpit-voucher-detail-campaign-explorer-return-link"
            >
              Back to Explorer · read-only
            </a>
            <a
              v-if="campaignDashboardReturnHref"
              :href="campaignDashboardReturnHref"
              class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
              data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"
            >
              Back to Campaign Dashboard · read-only
            </a>
          </div>
        </div>
        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="fact in [
              {
                label: 'Planning',
                value: campaignNavigationContext.planning_key,
              },
              {
                label: 'Execution',
                value: campaignNavigationContext.execution_id,
              },
              {
                label: 'Recipient',
                value:
                  campaignNavigationContext.recipient_id ?? 'recipient pending',
              },
              {
                label: 'Campaign',
                value:
                  campaignNavigationContext.campaign_id ?? 'campaign pending',
              },
              {
                label: 'Audience',
                value:
                  campaignNavigationContext.audience_id ?? 'audience pending',
              },
              { label: 'Source', value: campaignNavigationSourceLabel },
              {
                label: 'Current page',
                value: campaignNavigationDestinationLabel,
              },
              { label: 'Safety', value: campaignNavigationSafetyLabel },
              {
                label: 'Payload visibility',
                value: campaignNavigationPayloadLabel,
              },
            ]"
            :key="fact.label"
            class="min-w-0 rounded-lg bg-white/80 p-3 dark:bg-slate-950/70"
          >
            <dt
              class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
            >
              {{ fact.label }}
            </dt>
            <dd
              class="mt-1 truncate font-semibold text-slate-950 dark:text-slate-50"
              :title="fact.value"
            >
              {{ fact.value }}
            </dd>
          </div>
        </dl>
      </section>

      <CockpitPayCodeRecordWorkspace
        :code="code"
        :status="status"
        :voucher="props.read_model?.voucher"
        :journal="props.read_model?.journal"
        :feedback="props.read_model?.feedback"
        :redemption="redemption"
        :payout-institutions="props.payout_institutions ?? []"
        :claim-url="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
        :claim-qr="beneficiaryClaimQr"
        :distribution-url="distributionWorkspaceHref"
        :explorer-url="explorerHref"
        :terminal-control="props.terminal_control"
      />
    </section>
  </CockpitLayout>
</template>
