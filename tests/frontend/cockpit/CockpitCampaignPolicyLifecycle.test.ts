import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import CampaignPolicyLifecycle from "../../../resources/js/cockpit/pages/CampaignPolicyLifecycle.vue";

const lifecycle = {
  schema: "x-change.campaign-policy-lifecycle.v1" as const,
  stage: "policy_succeeded",
  attention_required: false,
  campaign: {
    reference: "01CAMPAIGN",
    name: "AUI On-Demand Insurance",
    revision_id: "revision-2",
  },
  payment: {
    recognition_reference: "01PAYMENT",
    gross_amount_minor: 12_200,
    currency: "PHP",
    settled_at: "2026-09-23T02:00:00+00:00",
  },
  coverage: {
    reference: "01COVERAGE",
    type: "personal_accident",
    status: "provisional",
    amount_minor: 100_000,
    currency: "PHP",
    effective_at: "2026-09-23T02:00:00+00:00",
    expires_at: "2026-09-24T02:00:00+00:00",
  },
  completion: {
    issuance_reference: "01ISSUANCE",
    pay_code: "AUI-ABCD",
    issued_at: "2026-09-23T02:01:00+00:00",
    claim_number: 1,
    claim_completed_at: "2026-09-23T02:02:00+00:00",
    projection_reference: "01PROJECTION",
    projected_at: "2026-09-23T02:02:00+00:00",
  },
  policy: {
    request_reference: "01REQUEST",
    status: "succeeded",
    requested_at: "2026-09-23T02:03:00+00:00",
    approved_at: "2026-09-23T02:04:00+00:00",
    outcome_reference: "01OUTCOME",
    outcome_status: "succeeded",
    result_code: "accepted",
    recorded_at: "2026-09-23T02:05:00+00:00",
  },
  updated_at: "2026-09-23T02:05:00+00:00",
};

describe("Cockpit campaign policy lifecycle", () => {
  it("renders a coherent read-only lifecycle without private or mutation surfaces", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: { lifecycles: [lifecycle] },
    });

    expect(wrapper.text()).toContain("AUI On-Demand Insurance");
    expect(wrapper.text()).toContain("Policy completed");
    expect(wrapper.text()).toContain("₱122.00");
    expect(wrapper.text()).toContain("Personal Accident");
    expect(wrapper.text()).toContain("AUI-ABCD");
    expect(wrapper.text()).toContain("Succeeded");
    expect(
      wrapper
        .get('[data-testid="campaign-policy-lifecycle-page"]')
        .findAll("button"),
    ).toHaveLength(0);
    expect(wrapper.text()).not.toContain("provider_transaction_key");
    expect(wrapper.text()).not.toContain("authorization_reference");
  });

  it("shows attention for a terminal failure and tolerates missing completion data", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: {
        lifecycles: [
          {
            ...lifecycle,
            stage: "policy_failed",
            attention_required: true,
            completion: null,
            policy: {
              ...lifecycle.policy,
              status: "failed",
              outcome_status: "failed",
              result_code: "declined",
            },
          },
        ],
      },
    });

    expect(wrapper.text()).toContain("Policy completion failed");
    expect(wrapper.text()).toContain("Needs attention");
    expect(wrapper.text()).toContain("Not issued");
    expect(
      wrapper
        .find('[data-testid="campaign-policy-lifecycle-attention"]')
        .exists(),
    ).toBe(true);
  });

  it("renders a clear empty state", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: { lifecycles: [] },
    });

    expect(wrapper.text()).toContain("No policy lifecycles yet");
    expect(
      wrapper.find('[data-testid="campaign-policy-lifecycle-empty"]').exists(),
    ).toBe(true);
  });
});
