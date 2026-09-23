<script setup lang="ts">
import { Head, Link, usePoll } from "@inertiajs/vue3";
import { show as scenarioRunnerShow } from "@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitLeadCampaignScenarioRunnerController";
import {
  AlertTriangle,
  ArrowLeft,
  ArrowUpRight,
  CheckCircle2,
  CircleDashed,
  Clock3,
  FileArchive,
  LoaderCircle,
} from "lucide-vue-next";
import CockpitLayout from "../layouts/CockpitLayout.vue";
import type { CockpitHeaderReadModel } from "../types";

defineOptions({ inheritAttrs: false });

type ScenarioStep = {
  sequence: number;
  label: string;
  status: string;
  description: string;
  occurred_at: string | null;
  facts: Record<string, unknown>;
};

type ScenarioArtifact = {
  group: string;
  label: string;
  reference: string | null;
  href: string | null;
  evidence: string;
};

type ScenarioRun = {
  schema: string;
  reference: string;
  scenario: string;
  title: string;
  mode: string;
  status: string;
  started_at: string | null;
  updated_at: string | null;
  declarations: Record<string, string>;
  steps: ScenarioStep[];
  artifacts: ScenarioArtifact[];
};

const props = defineProps<{
  cockpit_header_read_model?: CockpitHeaderReadModel;
  run: ScenarioRun;
}>();

usePoll(5000, { only: ["run"] });

function readable(value: string): string {
  return value.replaceAll("_", " ");
}

function timestamp(value: string | null): string {
  if (!value) return "No timestamp yet";

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "medium",
  }).format(new Date(value));
}

function iconFor(status: string) {
  if (status === "passed") return CheckCircle2;
  if (status === "failed" || status === "indeterminate") return AlertTriangle;
  if (status === "running") return LoaderCircle;
  if (status.startsWith("waiting_")) return Clock3;

  return CircleDashed;
}

function statusClass(status: string): string {
  if (status === "passed")
    return "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200";
  if (status === "failed" || status === "indeterminate")
    return "border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200";
  if (status === "running")
    return "border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200";
  if (status.startsWith("waiting_"))
    return "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200";

  return "border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300";
}
</script>

<template>
  <Head :title="`${props.run.title} · Scenario Run`" />

  <CockpitLayout
    active-navigation="campaigns"
    mobile-presentation="edge"
    :cockpit-header-read-model="props.cockpit_header_read_model"
  >
    <main
      class="mx-auto max-w-6xl space-y-5 px-4 py-4 md:px-0"
      data-testid="campaign-lifecycle-scenario-run"
    >
      <section
        class="-mx-4 border-y border-slate-200 bg-white p-4 md:mx-0 md:rounded-2xl md:border md:p-6 dark:border-slate-800 dark:bg-slate-900"
      >
        <Link
          :href="scenarioRunnerShow()"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
        >
          <ArrowLeft class="size-3.5" aria-hidden="true" />
          Scenario catalog
        </Link>

        <div
          class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
          <div class="min-w-0">
            <p
              class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
            >
              Browser lifecycle scenario
            </p>
            <h1
              class="mt-1 break-words text-2xl font-semibold text-slate-950 dark:text-slate-50"
            >
              {{ props.run.title }}
            </h1>
            <p
              class="mt-2 break-all font-mono text-xs text-slate-500 dark:text-slate-400"
            >
              {{ props.run.reference }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2 text-xs">
            <span
              class="rounded-full border px-3 py-1.5 font-semibold"
              :class="statusClass(props.run.status)"
            >
              {{ readable(props.run.status) }}
            </span>
            <span
              class="rounded-full border border-slate-200 px-3 py-1.5 text-slate-600 dark:border-slate-700 dark:text-slate-300"
            >
              {{ readable(props.run.mode) }}
            </span>
          </div>
        </div>

        <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="(value, key) in props.run.declarations"
            :key="key"
            class="min-w-0 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"
          >
            <dt
              class="truncate text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500"
            >
              {{ readable(String(key)) }}
            </dt>
            <dd
              class="mt-1 break-words text-sm font-medium text-slate-900 dark:text-slate-100"
            >
              {{ readable(value) }}
            </dd>
          </div>
        </dl>
      </section>

      <section
        class="grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]"
      >
        <div
          class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5 dark:border-slate-800 dark:bg-slate-900"
        >
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2
                class="text-base font-semibold text-slate-950 dark:text-slate-50"
              >
                Execution log
              </h2>
              <p class="mt-1 text-xs text-slate-500">
                Refreshes every five seconds from authoritative records.
              </p>
            </div>
            <span class="text-right text-[0.65rem] text-slate-400">{{
              timestamp(props.run.updated_at)
            }}</span>
          </div>

          <ol
            class="mt-5 space-y-3"
            data-testid="campaign-lifecycle-scenario-steps"
          >
            <li
              v-for="step in props.run.steps"
              :key="step.sequence"
              class="grid grid-cols-[2rem_minmax(0,1fr)] gap-3"
            >
              <div
                class="flex size-8 items-center justify-center rounded-full border"
                :class="statusClass(step.status)"
              >
                <component
                  :is="iconFor(step.status)"
                  class="size-4"
                  :class="step.status === 'running' ? 'animate-spin' : ''"
                  aria-hidden="true"
                />
              </div>
              <article
                class="min-w-0 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
              >
                <div
                  class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
                >
                  <h3
                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                  >
                    {{ step.sequence }}. {{ step.label }}
                  </h3>
                  <span
                    class="w-fit rounded-full border px-2 py-0.5 text-[0.65rem] font-semibold"
                    :class="statusClass(step.status)"
                  >
                    {{ readable(step.status) }}
                  </span>
                </div>
                <p
                  class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300"
                >
                  {{ step.description }}
                </p>
                <dl
                  v-if="Object.keys(step.facts).length"
                  class="mt-3 grid gap-2 sm:grid-cols-2"
                >
                  <div
                    v-for="(value, key) in step.facts"
                    :key="key"
                    class="min-w-0 rounded-lg bg-slate-50 px-2.5 py-2 dark:bg-slate-800/70"
                  >
                    <dt
                      class="truncate text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500"
                    >
                      {{ readable(String(key)) }}
                    </dt>
                    <dd
                      class="mt-0.5 break-all font-mono text-[0.7rem] text-slate-800 dark:text-slate-200"
                    >
                      {{ value }}
                    </dd>
                  </div>
                </dl>
                <p class="mt-2 text-[0.65rem] text-slate-400">
                  {{ timestamp(step.occurred_at) }}
                </p>
              </article>
            </li>
          </ol>
        </div>

        <aside
          class="h-fit rounded-2xl border border-slate-200 bg-white p-4 md:p-5 dark:border-slate-800 dark:bg-slate-900"
        >
          <div class="flex items-center gap-2">
            <FileArchive class="size-4 text-emerald-600" aria-hidden="true" />
            <h2
              class="text-base font-semibold text-slate-950 dark:text-slate-50"
            >
              Artifacts
            </h2>
          </div>
          <p class="mt-1 text-xs text-slate-500">
            Safe references only. Private applicant answers and provider secrets
            are excluded.
          </p>

          <div
            class="mt-4 space-y-3"
            data-testid="campaign-lifecycle-scenario-artifacts"
          >
            <article
              v-for="artifact in props.run.artifacts"
              :key="`${artifact.group}-${artifact.label}`"
              class="min-w-0 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p
                    class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500"
                  >
                    {{ artifact.group }} · {{ readable(artifact.evidence) }}
                  </p>
                  <h3
                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-slate-50"
                  >
                    {{ artifact.label }}
                  </h3>
                  <p
                    class="mt-1 break-all font-mono text-[0.7rem] text-slate-500"
                  >
                    {{ artifact.reference ?? "Reference pending" }}
                  </p>
                </div>
                <Link
                  v-if="artifact.href"
                  :href="artifact.href"
                  class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                  :aria-label="`Open ${artifact.label}`"
                >
                  <ArrowUpRight class="size-4" aria-hidden="true" />
                </Link>
              </div>
            </article>
          </div>
        </aside>
      </section>
    </main>
  </CockpitLayout>
</template>
