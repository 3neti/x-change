<script setup lang="ts">
import CockpitPayCodePayoutCorrectionController from "@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodePayoutCorrectionController";
import BankEMISelect from "@/components/financial/BankEMISelect.vue";
import CockpitPayCodeTerminalControls from "./CockpitPayCodeTerminalControls.vue";
import { Form } from "@inertiajs/vue3";
import {
  Activity,
  BadgeCheck,
  Banknote,
  CalendarClock,
  Check,
  ChevronRight,
  CircleDollarSign,
  CircleAlert,
  ClipboardCheck,
  Clock3,
  ExternalLink,
  Eye,
  FileCheck2,
  FileText,
  Fingerprint,
  History,
  Image,
  Landmark,
  MapPin,
  ReceiptText,
  RotateCcw,
  ScanFace,
  Send,
  ShieldCheck,
  Sparkles,
  UserRound,
} from "lucide-vue-next";
import { computed, ref } from "vue";
import type {
  CockpitDependentReadModel,
  CockpitMoneyIssuerOption,
  CockpitPayCodeTerminalControl,
  CockpitVoucherReadModel,
} from "../types";

type WorkspaceTab =
  | "overview"
  | "instructions"
  | "claim"
  | "settlement"
  | "audit";
type DetailRecord = Record<string, unknown>;

const props = defineProps<{
  code: string;
  status: string;
  voucher?: CockpitVoucherReadModel;
  journal?: CockpitDependentReadModel;
  feedback?: CockpitDependentReadModel;
  redemption?: Record<string, unknown> | null;
  payoutInstitutions?: CockpitMoneyIssuerOption[];
  claimUrl?: string | null;
  distributionUrl: string;
  explorerUrl: string;
  terminalControl?: CockpitPayCodeTerminalControl;
}>();

const activeTab = ref<WorkspaceTab>("overview");
const revealedEvidence = ref<Set<number>>(new Set());
const correctedBankCode = ref("");
const correctedAccountNumber = ref("");

const overview = computed(() => record(props.voucher?.overview));
const instructions = computed(() => record(props.voucher?.instructions));
const claims = computed(() => record(props.voucher?.claims));
const settlement = computed(() => record(props.voucher?.settlement));
const treasury = computed(() => record(props.voucher?.treasury));
const backing = computed(() => record(treasury.value.backing));
const party = computed(() => record(overview.value.party));
const amounts = computed(() => list(overview.value.amounts));
const primaryAmount = computed(
  () =>
    amounts.value.find((amount) => amount.primary === true) ??
    amounts.value[0] ??
    null,
);
const instructionGroups = computed(() => list(instructions.value.groups));
const claimRecords = computed(() => list(claims.value.records));
const evidence = computed(() => list(claims.value.evidence));
const evidenceCoverage = computed(() => {
  const requirements = claimRecords.value.reduce(
    (total, claim) => total + number(record(claim.evidence).required_count),
    0,
  );
  const captured = claimRecords.value.reduce(
    (total, claim) => total + number(record(claim.evidence).captured_count),
    0,
  );

  return {
    required: requirements,
    captured,
    complete: requirements > 0 && captured >= requirements,
  };
});
const evidenceGroups = computed(() => {
  const definitions = [
    { key: "identity", label: "Identity" },
    { key: "contact", label: "Contact & Address" },
    { key: "location", label: "Location" },
    { key: "media", label: "Selfie & Signature" },
    { key: "verification", label: "KYC & Verification" },
    { key: "other", label: "Other Evidence" },
  ];

  return definitions
    .map((definition) => ({
      ...definition,
      items: evidence.value.filter(
        (item) => text(item.group) === definition.key,
      ),
    }))
    .filter((group) => group.items.length > 0);
});
const hasLegacyEvidenceGap = computed(
  () =>
    evidence.value.length === 0 &&
    claimRecords.value.some(
      (claim) => number(record(claim.evidence).required_count) > 0,
    ),
);
const envelope = computed(() => record(settlement.value.envelope));
const journalEntries = computed(() => list(props.journal?.entries));
const deliveries = computed(() => list(props.feedback?.deliveries));
const redemption = computed(() => record(props.redemption));
const payoutRejected = computed(
  () =>
    text(redemption.value.payout_status) === "failed" ||
    text(redemption.value.status) === "failed" ||
    redemption.value.requires_recovery === true,
);
const canCorrectDestination = computed(
  () => redemption.value.can_correct_destination === true,
);
const selectedInstitution = computed(() =>
  (props.payoutInstitutions ?? []).find(
    (institution) => institution.value === correctedBankCode.value,
  ),
);
const correctedAccountLabel = computed(
  () => selectedInstitution.value?.account_label || "Account Number",
);
const correctionReview = computed(() => {
  const ending = correctedAccountNumber.value.replace(/\s+/g, "").slice(-4);

  if (!selectedInstitution.value) {
    return "Choose the receiving institution.";
  }

  return ending === ""
    ? `${selectedInstitution.value.name} · enter ${correctedAccountLabel.value.toLocaleLowerCase()}`
    : `${selectedInstitution.value.name} · ••••${ending}`;
});
const rejectionReason = computed(
  () =>
    text(redemption.value.rejection_reason) ||
    "The receiving institution rejected the payout destination.",
);

const tabs = computed(() => [
  {
    key: "overview" as const,
    label: "Overview",
    icon: ReceiptText,
    count: null,
  },
  {
    key: "instructions" as const,
    label: "Instructions",
    icon: ClipboardCheck,
    count: instructionGroups.value.length,
  },
  {
    key: "claim" as const,
    label: "Claim & Evidence",
    icon: Fingerprint,
    count: claimRecords.value.length + evidence.value.length,
  },
  {
    key: "settlement" as const,
    label: "Settlement",
    icon: Landmark,
    count: envelope.value.available === true ? 1 : 0,
  },
  {
    key: "audit" as const,
    label: "Audit",
    icon: History,
    count: journalEntries.value.length + deliveries.value.length,
  },
]);

const backingTone = computed(() => {
  const mode = text(backing.value.mode);
  const status = text(backing.value.status);

  if (mode === "treasury_position" && status === "settled") {
    return "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200";
  }

  if (mode === "treasury_position") {
    return "border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/60 dark:text-blue-200";
  }

  if (mode === "legacy_cash_entity") {
    return "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200";
  }

  return "border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300";
});

const backingExplanation = computed(() => {
  if (text(backing.value.mode) === "treasury_position") {
    return "Principal is recognized in provider-attributed Treasury Positions. A legacy Cash entity is not the monetary authority.";
  }

  if (text(backing.value.mode) === "legacy_cash_entity") {
    return "This older Pay Code stores value through its attached Cash entity. It does not by itself prove current provider liquidity.";
  }

  return "No authoritative monetary backing record is available in this read model.";
});

const envelopeProgress = computed(() => {
  const checklist = record(envelope.value.checklist);
  const total = number(checklist.required_count);
  const complete = number(checklist.required_completed);

  return total > 0 ? Math.round((complete / total) * 100) : 0;
});

function revealEvidence(id: number): void {
  revealedEvidence.value = new Set([...revealedEvidence.value, id]);
}

function instructionIcon(key: unknown) {
  return (
    {
      value: Banknote,
      claim: ShieldCheck,
      experience: Sparkles,
      delivery: Send,
      controls: CalendarClock,
    }[text(key)] ?? FileText
  );
}

function evidenceIcon(kind: unknown) {
  return (
    {
      image: Image,
      location: MapPin,
      verification: BadgeCheck,
      text: FileText,
    }[text(kind)] ?? FileText
  );
}

function formatMoney(value: unknown, currency: unknown = "PHP"): string {
  const minor = number(value);
  const code = text(currency) || "PHP";

  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: code,
    minimumFractionDigits: 2,
  }).format(minor / 100);
}

function formatDate(value: unknown): string {
  const normalized = text(value);

  if (!normalized) {
    return "Not recorded";
  }

  const date = new Date(normalized);

  return Number.isNaN(date.getTime())
    ? normalized
    : new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(date);
}

function claimTimingLabel(claim: DetailRecord): string {
  if (text(claim.completed_at)) {
    return text(claim.status) === "paid"
      ? "Payout completed"
      : "Claim completed";
  }

  return "Claim attempted";
}

function title(value: unknown): string {
  const normalized = text(value).replace(/[._-]+/g, " ");

  return normalized.replace(/\b\w/g, (character) => character.toUpperCase());
}

function record(value: unknown): DetailRecord {
  return typeof value === "object" && value !== null && !Array.isArray(value)
    ? (value as DetailRecord)
    : {};
}

function list(value: unknown): DetailRecord[] {
  return Array.isArray(value)
    ? value.filter(
        (item): item is DetailRecord =>
          typeof item === "object" && item !== null && !Array.isArray(item),
      )
    : [];
}

function text(value: unknown): string {
  return typeof value === "string" || typeof value === "number"
    ? String(value)
    : "";
}

function number(value: unknown): number {
  return typeof value === "number" ? value : Number(value) || 0;
}
</script>

<template>
  <section
    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_24px_70px_-42px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-900"
    data-testid="pay-code-record-workspace"
  >
    <header
      class="relative overflow-hidden border-b border-slate-200 bg-slate-950 px-5 py-5 text-white dark:border-slate-800 sm:px-6"
    >
      <div
        class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-emerald-400/15 blur-3xl"
      ></div>
      <div
        class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-blue-500/15 blur-3xl"
      ></div>
      <div
        class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between"
      >
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-slate-200"
            >
              {{
                title(
                  overview.capability && record(overview.capability).label,
                ) || "Pay Code"
              }}
            </span>
            <span
              :class="[
                'rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold',
                backingTone,
              ]"
            >
              {{ text(backing.label) || "Backing Unverified" }} ·
              {{ title(backing.status) || "Not Available" }}
            </span>
          </div>
          <div class="mt-4 flex flex-wrap items-baseline gap-x-4 gap-y-2">
            <h2
              class="font-mono text-3xl font-black tracking-[0.16em] sm:text-4xl"
            >
              {{ code }}
            </h2>
            <span class="text-sm font-semibold text-slate-300">{{
              title(status)
            }}</span>
          </div>
          <p class="mt-2 flex items-center gap-2 text-sm text-slate-300">
            <UserRound class="h-4 w-4" />
            <span>{{ text(party.label) || "Availability" }}:</span>
            <strong class="text-white">{{
              text(party.primary) || "Open claim"
            }}</strong>
          </p>
        </div>
        <div class="flex items-end justify-between gap-5 xl:justify-end">
          <div class="text-left xl:text-right">
            <p
              class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-400"
            >
              {{ text(primaryAmount?.label) || "Pay Code Value" }}
            </p>
            <p class="mt-1 text-3xl font-bold tracking-tight sm:text-4xl">
              {{
                formatMoney(
                  primaryAmount?.amount_minor,
                  primaryAmount?.currency,
                )
              }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ title(primaryAmount?.authority) || "Read model" }}
            </p>
          </div>
          <a
            v-if="claimUrl"
            :href="claimUrl"
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20"
            title="Open claim URL"
          >
            <ExternalLink class="h-4 w-4" />
          </a>
        </div>
      </div>
    </header>

    <nav
      class="overflow-x-auto border-b border-slate-200 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/40"
      aria-label="Pay Code record sections"
    >
      <div class="flex min-w-max gap-1" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.key"
          :class="[
            'inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition',
            activeTab === tab.key
              ? 'bg-white text-slate-950 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-white dark:ring-slate-700'
              : 'text-slate-500 hover:bg-white/70 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-white',
          ]"
          @click="activeTab = tab.key"
        >
          <component :is="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
          <span
            v-if="tab.count"
            class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[0.6rem] dark:bg-slate-700"
            >{{ tab.count }}</span
          >
        </button>
      </div>
    </nav>

    <div class="min-h-[28rem] p-4 sm:p-6">
      <section
        v-if="activeTab === 'overview'"
        class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)]"
        data-testid="pay-code-overview-tab"
      >
        <article
          v-if="payoutRejected"
          class="flex flex-col gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 sm:flex-row sm:items-center sm:justify-between xl:col-span-2 dark:border-rose-900/70 dark:bg-rose-950/30"
          data-testid="pay-code-overview-payout-warning"
        >
          <div class="flex min-w-0 items-start gap-3">
            <span
              class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-200"
            >
              <CircleAlert class="size-4" />
            </span>
            <div class="min-w-0">
              <h3
                class="text-sm font-semibold text-rose-950 dark:text-rose-100"
              >
                Claim completed · Payout needs correction
              </h3>
              <p
                class="mt-1 text-xs leading-5 text-rose-800 dark:text-rose-200"
              >
                {{ rejectionReason }} The principal remains protected for this
                beneficiary.
              </p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-800 transition hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-100 dark:hover:bg-rose-900/60"
            data-testid="pay-code-overview-review-correction"
            @click="activeTab = 'claim'"
          >
            Review correction
            <ChevronRight class="size-4" />
          </button>
        </article>
        <div class="space-y-5">
          <div>
            <div class="flex items-center gap-2">
              <CircleDollarSign class="h-5 w-5 text-emerald-600" />
              <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
                Value Ledger
              </h3>
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
              <article
                v-for="amount in amounts"
                :key="text(amount.key)"
                :class="[
                  'rounded-2xl border p-3',
                  amount.primary === true
                    ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
                    : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/50',
                ]"
              >
                <p
                  class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400"
                >
                  {{ text(amount.label) }}
                </p>
                <p
                  class="mt-1 text-xl font-bold text-slate-950 dark:text-white"
                >
                  {{ formatMoney(amount.amount_minor, amount.currency) }}
                </p>
                <p
                  class="mt-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
                >
                  Authority: {{ title(amount.authority) }}
                </p>
              </article>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <article
              class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <div
                class="flex items-center gap-2 text-slate-500 dark:text-slate-400"
              >
                <UserRound class="h-4 w-4" /><span class="text-xs font-semibold"
                  >Recipient</span
                >
              </div>
              <p class="mt-2 font-semibold text-slate-950 dark:text-white">
                {{ text(party.primary) || "Open claim" }}
              </p>
              <p
                v-if="party.secondary"
                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
              >
                {{ text(party.secondary) }}
              </p>
            </article>
            <article
              class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <div
                class="flex items-center gap-2 text-slate-500 dark:text-slate-400"
              >
                <ShieldCheck class="h-4 w-4" /><span
                  class="text-xs font-semibold"
                  >Accounting Backing</span
                >
              </div>
              <p class="mt-2 font-semibold text-slate-950 dark:text-white">
                {{ text(backing.label) || "Backing Unverified" }}
              </p>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ title(backing.mode) }} · {{ title(backing.status) }}
              </p>
              <p
                class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
              >
                {{ backingExplanation }}
              </p>
            </article>
          </div>
        </div>

        <aside
          class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50"
        >
          <div class="flex items-center gap-2">
            <Clock3 class="h-4 w-4 text-slate-500" />
            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
              Lifecycle
            </h3>
          </div>
          <ol class="mt-4 space-y-4">
            <li
              v-for="item in [
                { label: 'Issued', value: record(overview.timing).issued_at },
                {
                  label: 'Available From',
                  value: record(overview.timing).starts_at,
                },
                { label: 'Expires', value: record(overview.timing).expires_at },
                {
                  label: 'Voucher Closed',
                  value: record(overview.timing).redeemed_at,
                },
              ]"
              :key="item.label"
              class="relative pl-6"
            >
              <span
                class="absolute left-0 top-1 h-2.5 w-2.5 rounded-full bg-slate-300 ring-4 ring-white dark:bg-slate-600 dark:ring-slate-950"
              ></span>
              <p
                class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
              >
                {{ item.label }}
              </p>
              <p
                class="mt-0.5 text-xs font-medium text-slate-900 dark:text-slate-100"
              >
                {{ formatDate(item.value) }}
              </p>
            </li>
          </ol>
          <p
            class="mt-4 text-[0.7rem] leading-5 text-slate-500 dark:text-slate-400"
          >
            Voucher Closed marks the lifecycle transition. Payout completion is
            recorded separately under Claim &amp; Evidence.
          </p>
        </aside>
        <CockpitPayCodeTerminalControls
          v-if="terminalControl"
          :code="code"
          :control="terminalControl"
        />
      </section>

      <section
        v-else-if="activeTab === 'instructions'"
        class="space-y-4"
        data-testid="pay-code-instructions-tab"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
              What This Pay Code Asks For
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              The issued instruction snapshot, translated into operational
              language.
            </p>
          </div>
          <span
            class="rounded-full bg-slate-100 px-2.5 py-1 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >Immutable Snapshot</span
          >
        </div>
        <div
          v-if="instructionGroups.length"
          class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
        >
          <article
            v-for="group in instructionGroups"
            :key="text(group.key)"
            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
          >
            <div class="flex items-center gap-2">
              <span
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                ><component :is="instructionIcon(group.key)" class="h-4 w-4"
              /></span>
              <h4 class="text-sm font-semibold text-slate-950 dark:text-white">
                {{ text(group.label) }}
              </h4>
            </div>
            <dl class="mt-4 space-y-3">
              <div v-for="fact in list(group.facts)" :key="text(fact.label)">
                <dt
                  class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                >
                  {{ text(fact.label) }}
                </dt>
                <dd
                  class="mt-0.5 break-words text-xs font-medium leading-5 text-slate-900 dark:text-slate-100"
                >
                  {{ text(fact.value) }}
                </dd>
              </div>
            </dl>
          </article>
        </div>
        <div
          v-else
          class="rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700"
        >
          <FileText class="mx-auto h-6 w-6 text-slate-400" />
          <p
            class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
          >
            No instruction snapshot is available.
          </p>
        </div>
      </section>

      <section
        v-else-if="activeTab === 'claim'"
        class="space-y-5"
        data-testid="pay-code-claim-tab"
      >
        <article
          v-if="payoutRejected"
          class="rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/70 dark:bg-rose-950/30"
          data-testid="pay-code-payout-rejected"
        >
          <div class="flex items-start gap-3">
            <span
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-200"
            >
              <CircleAlert class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3
                    class="text-sm font-semibold text-rose-950 dark:text-rose-100"
                  >
                    Claim recorded · Payout rejected
                  </h3>
                  <p
                    class="mt-1 text-xs leading-5 text-rose-800 dark:text-rose-200"
                  >
                    {{ rejectionReason }} The principal remains protected for
                    this beneficiary and was not returned to the issuer.
                  </p>
                </div>
                <span
                  class="rounded-full bg-rose-100 px-2.5 py-1 text-[0.65rem] font-semibold text-rose-800 dark:bg-rose-900/60 dark:text-rose-100"
                >
                  Destination correction required
                </span>
              </div>

              <Form
                v-if="canCorrectDestination"
                v-bind="CockpitPayCodePayoutCorrectionController.form({ code })"
                reset-on-success
                #default="{ errors, processing }"
                class="mt-4 grid gap-3 border-t border-rose-200 pt-4 sm:grid-cols-2 xl:grid-cols-[minmax(13rem,0.9fr)_minmax(12rem,1fr)_minmax(11rem,0.8fr)_auto] dark:border-rose-900/70"
                data-testid="pay-code-payout-correction-form"
              >
                <label
                  class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >
                  Bank or Wallet
                  <input
                    type="hidden"
                    name="bank_code"
                    :value="correctedBankCode"
                  />
                  <BankEMISelect
                    v-model="correctedBankCode"
                    :settlement-rail="text(redemption.settlement_rail) || 'INSTAPAY'"
                    :institutions="payoutInstitutions ?? []"
                    :disabled="processing"
                  />
                  <span
                    v-if="errors.bank_code"
                    class="font-normal text-rose-700 dark:text-rose-300"
                    >{{ errors.bank_code }}</span
                  >
                </label>
                <label
                  class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >
                  Correct {{ correctedAccountLabel }}
                  <input
                    v-model="correctedAccountNumber"
                    name="account_number"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="Enter the corrected destination"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-rose-900"
                  />
                  <span
                    v-if="errors.account_number"
                    class="font-normal text-rose-700 dark:text-rose-300"
                    >{{ errors.account_number }}</span
                  >
                </label>
                <label
                  class="grid gap-1 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >
                  Contact Mobile
                  <span class="font-normal text-slate-500">(optional)</span>
                  <input
                    name="mobile"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="09•••••••••"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-950 outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-rose-900"
                  />
                  <span
                    v-if="errors.mobile"
                    class="font-normal text-rose-700 dark:text-rose-300"
                    >{{ errors.mobile }}</span
                  >
                </label>
                <button
                  type="submit"
                  :disabled="processing"
                  class="inline-flex h-10 self-end items-center justify-center gap-2 rounded-xl bg-rose-700 px-4 text-xs font-semibold text-white transition hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <RotateCcw class="h-4 w-4" />
                  {{
                    processing
                      ? "Checking & submitting…"
                      : "Correct & retry payout"
                  }}
                </button>
                <div class="sm:col-span-2 xl:col-span-4">
                  <p
                    class="text-[0.7rem] font-semibold text-rose-900 dark:text-rose-100"
                    data-testid="pay-code-payout-correction-review"
                  >
                    Review destination: {{ correctionReview }}
                  </p>
                  <p
                    class="mt-1 text-[0.7rem] leading-5 text-rose-800 dark:text-rose-200"
                  >
                    x-change checks the institution and account format before
                    submission. When the provider has no account-inquiry
                    service, the receiving institution still makes the final
                    decision. The original claim and rejected attempt remain
                    unchanged.
                  </p>
                </div>
              </Form>
            </div>
          </div>
        </article>

        <article
          v-if="evidenceCoverage.required > 0"
          :class="[
            'flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between',
            evidenceCoverage.complete
              ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
              : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
          ]"
          data-testid="claim-evidence-coverage"
        >
          <div class="flex items-center gap-3">
            <span
              :class="[
                'inline-flex size-9 shrink-0 items-center justify-center rounded-xl',
                evidenceCoverage.complete
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-200'
                  : 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-200',
              ]"
            >
              <Check v-if="evidenceCoverage.complete" class="size-4" />
              <CircleAlert v-else class="size-4" />
            </span>
            <div>
              <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
                Evidence Coverage
              </h3>
              <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                {{ evidenceCoverage.captured }} of
                {{ evidenceCoverage.required }} required items captured
              </p>
            </div>
          </div>
          <span
            class="self-start rounded-full bg-white/80 px-2.5 py-1 text-[0.65rem] font-semibold text-slate-700 ring-1 ring-black/5 dark:bg-slate-900/70 dark:text-slate-200 dark:ring-white/10 sm:self-auto"
          >
            {{ evidenceCoverage.complete ? "Complete" : "Needs Attention" }}
          </span>
        </article>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
          <div>
            <div class="flex items-center gap-2">
              <ScanFace class="h-5 w-5 text-blue-600" />
              <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
                Claims
              </h3>
            </div>
            <div v-if="claimRecords.length" class="mt-3 space-y-3">
              <article
                v-for="claim in claimRecords"
                :key="number(claim.id)"
                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p
                      class="text-xs font-semibold text-slate-950 dark:text-white"
                    >
                      Claim {{ text(claim.claim_number) || "1" }}
                    </p>
                    <p
                      class="mt-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
                    >
                      {{ title(claim.claim_type) }} ·
                      {{ claimTimingLabel(claim) }} ·
                      {{ formatDate(claim.completed_at || claim.attempted_at) }}
                    </p>
                  </div>
                  <span
                    class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold dark:bg-slate-800"
                    >{{ title(claim.status) }}</span
                  >
                </div>
                <div
                  v-if="number(record(claim.evidence).required_count) > 0"
                  class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-[0.7rem] dark:bg-slate-950/60"
                >
                  <span class="text-slate-500 dark:text-slate-400"
                    >Evidence</span
                  >
                  <strong class="text-slate-800 dark:text-slate-100">
                    {{ number(record(claim.evidence).captured_count) }} /
                    {{ number(record(claim.evidence).required_count) }}
                  </strong>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                  <div>
                    <dt class="text-slate-500">Paid</dt>
                    <dd
                      class="mt-0.5 font-semibold text-slate-950 dark:text-white"
                    >
                      {{
                        formatMoney(
                          claim.disbursed_amount_minor,
                          claim.currency,
                        )
                      }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-slate-500">Claimant</dt>
                    <dd
                      class="mt-0.5 font-semibold text-slate-950 dark:text-white"
                    >
                      {{
                        text(claim.claimer_mobile_masked) ||
                        "Identity protected"
                      }}
                    </dd>
                  </div>
                  <div v-if="claim.bank_code">
                    <dt class="text-slate-500">Destination</dt>
                    <dd
                      class="mt-0.5 font-semibold text-slate-950 dark:text-white"
                    >
                      {{ text(claim.bank_code) }} ·
                      {{ text(claim.account_number_masked) }}
                    </dd>
                  </div>
                  <div v-if="claim.reference">
                    <dt class="text-slate-500">Reference</dt>
                    <dd
                      class="mt-0.5 truncate font-mono font-semibold text-slate-950 dark:text-white"
                    >
                      {{ text(claim.reference) }}
                    </dd>
                  </div>
                </dl>
              </article>
            </div>
            <div
              v-else
              class="mt-3 rounded-2xl border border-dashed border-slate-300 p-6 text-center dark:border-slate-700"
            >
              <Fingerprint class="mx-auto h-6 w-6 text-slate-400" />
              <p
                class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
              >
                Not yet claimed
              </p>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <FileCheck2 class="h-5 w-5 text-violet-600" />
                <h3
                  class="text-sm font-semibold text-slate-950 dark:text-white"
                >
                  Captured Evidence
                </h3>
              </div>
              <span class="text-[0.65rem] font-semibold text-slate-500"
                >{{ evidence.length }} items</span
              >
            </div>
            <div v-if="evidence.length" class="mt-3 space-y-4">
              <section v-for="group in evidenceGroups" :key="group.key">
                <div class="mb-2 flex items-center justify-between gap-3">
                  <h4
                    class="text-[0.7rem] font-semibold uppercase tracking-[0.13em] text-slate-500 dark:text-slate-400"
                  >
                    {{ group.label }}
                  </h4>
                  <span class="text-[0.65rem] text-slate-400">{{
                    group.items.length
                  }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                  <article
                    v-for="item in group.items"
                    :key="number(item.id)"
                    class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800"
                  >
                    <div
                      v-if="item.revealable === true"
                      class="aspect-[16/10] bg-slate-100 dark:bg-slate-950"
                    >
                      <img
                        v-if="revealedEvidence.has(number(item.id))"
                        :src="text(item.reveal_href)"
                        :alt="text(item.label)"
                        class="h-full w-full object-contain"
                      />
                      <button
                        v-else
                        type="button"
                        class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-500 transition hover:bg-slate-200/60 dark:hover:bg-slate-800"
                        @click="revealEvidence(number(item.id))"
                      >
                        <Eye class="h-6 w-6" />
                        <span class="text-xs font-semibold"
                          >Reveal {{ text(item.label) }}</span
                        >
                        <span class="text-[0.65rem]">Access is recorded</span>
                      </button>
                    </div>
                    <div class="p-3">
                      <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2">
                          <component
                            :is="evidenceIcon(item.kind)"
                            class="h-4 w-4 shrink-0 text-slate-500"
                          />
                          <p
                            class="truncate text-xs font-semibold text-slate-950 dark:text-white"
                          >
                            {{ text(item.label) }}
                          </p>
                        </div>
                        <span
                          v-if="item.claim_number"
                          class="shrink-0 text-[0.6rem] font-semibold text-slate-400"
                        >
                          Claim {{ text(item.claim_number) }}
                        </span>
                      </div>
                      <p
                        class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300"
                      >
                        {{ text(item.value) || title(item.status) }}
                      </p>
                      <p
                        v-if="
                          ['missing', 'unavailable'].includes(
                            text(item.artifact_status),
                          )
                        "
                        class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-2 text-[0.68rem] leading-4 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200"
                      >
                        Private file unavailable. Its captured evidence record
                        and summary remain retained.
                      </p>
                    </div>
                  </article>
                </div>
              </section>
            </div>
            <div
              v-else
              class="mt-3 rounded-2xl border border-dashed border-slate-300 p-6 text-center dark:border-slate-700"
            >
              <Image class="mx-auto h-6 w-6 text-slate-400" />
              <p
                class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
              >
                {{
                  hasLegacyEvidenceGap
                    ? "Evidence was not retained for this legacy claim"
                    : "No captured evidence"
                }}
              </p>
              <p
                v-if="hasLegacyEvidenceGap"
                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
              >
                This Pay Code was claimed before durable per-claim evidence
                capture was enabled.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section
        v-else-if="activeTab === 'settlement'"
        class="space-y-5"
        data-testid="pay-code-settlement-tab"
      >
        <div
          v-if="envelope.available === true"
          class="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]"
        >
          <aside class="rounded-2xl bg-slate-950 p-5 text-white">
            <div class="flex items-center gap-2 text-slate-300">
              <Landmark class="h-4 w-4" /><span class="text-xs font-semibold"
                >Settlement Envelope</span
              >
            </div>
            <p class="mt-4 font-mono text-lg font-bold">
              {{ text(envelope.reference) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ text(envelope.driver) }}@{{ text(envelope.driver_version) }}
            </p>
            <div class="mt-5">
              <div class="flex justify-between text-xs">
                <span>Required evidence</span
                ><strong>{{ envelopeProgress }}%</strong>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                <div
                  class="h-full rounded-full bg-emerald-400 transition-all"
                  :style="{ width: `${envelopeProgress}%` }"
                ></div>
              </div>
            </div>
            <div class="mt-5 flex items-center gap-2">
              <span
                :class="[
                  'h-2.5 w-2.5 rounded-full',
                  envelope.settleable === true
                    ? 'bg-emerald-400'
                    : 'bg-amber-400',
                ]"
              ></span
              ><span class="text-xs font-semibold">{{
                envelope.settleable === true
                  ? "Ready for execution"
                  : "Readiness incomplete"
              }}</span>
            </div>
          </aside>
          <div class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <article
                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
              >
                <p
                  class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500"
                >
                  Envelope Status
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-white">
                  {{ title(envelope.status) }}
                </p>
              </article>
              <article
                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
              >
                <p
                  class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500"
                >
                  Payload Version
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-white">
                  {{ text(envelope.payload_version) }}
                </p>
              </article>
            </div>
            <div v-if="list(envelope.gates).length">
              <h4 class="text-xs font-semibold text-slate-950 dark:text-white">
                Readiness Gates
              </h4>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="gate in list(envelope.gates)"
                  :key="text(gate.key)"
                  :class="[
                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[0.65rem] font-semibold',
                    gate.satisfied === true
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                      : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
                  ]"
                  ><Check v-if="gate.satisfied === true" class="h-3 w-3" />{{
                    text(gate.label)
                  }}</span
                >
              </div>
            </div>
            <div v-if="list(envelope.attachments).length">
              <h4 class="text-xs font-semibold text-slate-950 dark:text-white">
                Documents
              </h4>
              <div
                class="mt-2 divide-y divide-slate-200 rounded-2xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
              >
                <a
                  v-for="attachment in list(envelope.attachments)"
                  :key="number(attachment.id)"
                  :href="text(attachment.reveal_href)"
                  target="_blank"
                  class="flex items-center justify-between gap-3 p-3 text-xs transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                  ><span class="flex min-w-0 items-center gap-2"
                    ><FileText class="h-4 w-4 shrink-0 text-slate-500" /><span
                      class="truncate font-semibold text-slate-950 dark:text-white"
                      >{{ text(attachment.label) }}</span
                    ></span
                  ><span class="flex items-center gap-2 text-slate-500"
                    >{{ title(attachment.review_status)
                    }}<ChevronRight class="h-3.5 w-3.5" /></span
                ></a>
              </div>
            </div>
          </div>
        </div>
        <div
          v-else
          class="rounded-3xl border border-dashed border-slate-300 p-10 text-center dark:border-slate-700"
        >
          <FileCheck2 class="mx-auto h-8 w-8 text-slate-400" />
          <h3
            class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100"
          >
            No Settlement Envelope
          </h3>
          <p
            class="mx-auto mt-1 max-w-lg text-xs leading-5 text-slate-500 dark:text-slate-400"
          >
            This Pay Code does not require a separate evidence-and-authorization
            envelope.
          </p>
        </div>

        <div
          class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"
        >
          <div class="flex items-start gap-3">
            <Landmark
              class="mt-0.5 h-5 w-5 shrink-0 text-blue-700 dark:text-blue-300"
            />
            <div>
              <h4
                class="text-sm font-semibold text-blue-950 dark:text-blue-100"
              >
                {{ text(backing.label) || "Backing Unverified" }}
              </h4>
              <p
                class="mt-1 text-xs leading-5 text-blue-800 dark:text-blue-200"
              >
                The Settlement Envelope proves readiness.
                {{ text(backing.label) || "The accounting record" }} proves
                where the money is held. They are deliberately separate.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section v-else class="space-y-5" data-testid="pay-code-audit-tab">
        <div class="grid gap-5 xl:grid-cols-2">
          <div>
            <div class="flex items-center gap-2">
              <Activity class="h-5 w-5 text-slate-600" />
              <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
                Journal
              </h3>
            </div>
            <div v-if="journalEntries.length" class="mt-3 space-y-1">
              <article
                v-for="(entry, index) in journalEntries"
                :key="text(entry.id) || index"
                class="relative border-l border-slate-200 py-3 pl-5 dark:border-slate-700"
              >
                <span
                  class="absolute -left-1 top-4 h-2 w-2 rounded-full bg-slate-400"
                ></span>
                <p class="text-xs font-semibold text-slate-950 dark:text-white">
                  {{ title(entry.event_type || entry.type || entry.event) }}
                </p>
                <p
                  class="mt-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
                >
                  {{ formatDate(entry.occurred_at || entry.created_at) }}
                </p>
              </article>
            </div>
            <p
              v-else
              class="mt-3 rounded-2xl border border-dashed border-slate-300 p-5 text-center text-xs text-slate-500 dark:border-slate-700"
            >
              No journal evidence is connected.
            </p>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <Send class="h-5 w-5 text-slate-600" />
              <h3 class="text-sm font-semibold text-slate-950 dark:text-white">
                Delivery
              </h3>
            </div>
            <div
              v-if="deliveries.length"
              class="mt-3 divide-y divide-slate-200 rounded-2xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
            >
              <article
                v-for="(delivery, index) in deliveries"
                :key="text(delivery.id) || index"
                class="flex items-center justify-between gap-3 p-3"
              >
                <div>
                  <p
                    class="text-xs font-semibold text-slate-950 dark:text-white"
                  >
                    {{ title(delivery.channel || delivery.type) }}
                  </p>
                  <p class="mt-1 text-[0.7rem] text-slate-500">
                    {{
                      title(text(delivery.provider_status).toLowerCase()) ||
                      "Provider detail protected"
                    }}
                  </p>
                  <p
                    v-if="delivery.delivered_at || delivery.last_attempted_at"
                    class="mt-1 text-[0.7rem] text-slate-500"
                  >
                    {{
                      delivery.delivered_at
                        ? "Delivered " + formatDate(delivery.delivered_at)
                        : "Last attempted " +
                          formatDate(delivery.last_attempted_at)
                    }}
                  </p>
                </div>
                <span
                  class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold dark:bg-slate-800"
                  >{{ title(delivery.status) }}</span
                >
              </article>
            </div>
            <p
              v-else
              class="mt-3 rounded-2xl border border-dashed border-slate-300 p-5 text-center text-xs text-slate-500 dark:border-slate-700"
            >
              No delivery evidence is connected.
            </p>
          </div>
        </div>
      </section>
    </div>

    <footer
      class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40 sm:px-6"
    >
      <a
        :href="explorerUrl"
        class="text-xs font-semibold text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
        >← Pay Codes</a
      >
      <a
        :href="distributionUrl"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
        ><Send class="h-4 w-4" />Distribution<ChevronRight class="h-3.5 w-3.5"
      /></a>
    </footer>
  </section>
</template>
