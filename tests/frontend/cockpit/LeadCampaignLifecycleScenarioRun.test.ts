import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import LeadCampaignLifecycleScenarioRun from "../../../resources/js/cockpit/pages/LeadCampaignLifecycleScenarioRun.vue";

vi.mock("@inertiajs/vue3", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@inertiajs/vue3")>()),
  Head: { template: "<div><slot /></div>" },
  Link: {
    props: ["href"],
    template:
      "<a :href=\"typeof href === 'string' ? href : href.url\"><slot /></a>",
  },
  usePoll: vi.fn(),
}));

const run = {
  schema: "x-change.cockpit.lead-campaign-lifecycle-run.v1",
  reference: "RUN-AUI-EXAMPLE",
  scenario: "aui_on_demand_insurance_payment",
  title: "AUI On-Demand Insurance Payment",
  mode: "browser_manual_payment",
  status: "running",
  started_at: "2026-09-24T01:00:00+00:00",
  updated_at: "2026-09-24T01:01:00+00:00",
  declarations: {
    payment_evidence: "not_observed",
    financial_mode: "real_provider_when_paid",
    insurer_contract: "not_configured",
    driver_authority: "demonstration_only",
  },
  steps: [
    {
      sequence: 1,
      label: "Preconditions checked",
      status: "passed",
      description: "The operator and scenario gate were accepted.",
      occurred_at: "2026-09-24T01:00:00+00:00",
      facts: { run_reference: "RUN-AUI-EXAMPLE" },
    },
    {
      sequence: 5,
      label: "Campaign endpoint opened",
      status: "waiting_for_person",
      description: "Open the public endpoint to continue.",
      occurred_at: null,
      facts: {},
    },
    {
      sequence: 10,
      label: "Provider payment observed",
      status: "waiting_for_provider",
      description: "No payment is inferred from QR generation.",
      occurred_at: null,
      facts: {},
    },
  ],
  artifacts: [
    {
      group: "Campaign",
      label: "Public endpoint",
      reference: "/x/o/aui/demo",
      href: "https://example.test/x/o/aui/demo",
      evidence: "application_persisted",
    },
    {
      group: "Settlement",
      label: "Envelope driver",
      reference: "aui.personal-accident.provisional-cover@1.0.0",
      href: null,
      evidence: "configuration",
    },
  ],
};

describe("Lead Campaign lifecycle scenario run", () => {
  it("presents the chronological log, evidence declarations, and safe artifacts", () => {
    const wrapper = mount(LeadCampaignLifecycleScenarioRun, { props: { run } });

    expect(wrapper.text()).toContain("RUN-AUI-EXAMPLE");
    expect(wrapper.text()).toContain("Execution log");
    expect(wrapper.text()).toContain("Preconditions checked");
    expect(wrapper.text()).toContain("waiting for person");
    expect(wrapper.text()).toContain("Provider payment observed");
    expect(wrapper.text()).toContain(
      "No payment is inferred from QR generation.",
    );
    expect(wrapper.text()).toContain("Artifacts");
    expect(wrapper.text()).toContain("Envelope driver");
    expect(wrapper.text()).toContain("demonstration only");
    expect(
      wrapper.get('[aria-label="Open Public endpoint"]').attributes("href"),
    ).toBe("https://example.test/x/o/aui/demo");
    expect(wrapper.text()).not.toContain("otp_code");
    expect(wrapper.text()).not.toContain("provider_secret");
  });
});
