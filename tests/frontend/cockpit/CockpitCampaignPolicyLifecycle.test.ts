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
  it.each(["failed", "indeterminate"])(
    "keeps %s demo-coded outcomes in attention state",
    (status) => {
      const wrapper = mount(CampaignPolicyLifecycle, {
        props: {
          lifecycles: [
            {
              ...lifecycle,
              stage: `policy_${status}`,
              attention_required: true,
              policy: {
                ...lifecycle.policy,
                status,
                outcome_status: status,
                result_code: "policy_issued_demo",
              },
            },
          ],
        },
      });
      expect(wrapper.text()).not.toContain("Demo summary ready");
      expect(
        wrapper
          .find('[data-testid="campaign-policy-lifecycle-attention"]')
          .exists(),
      ).toBe(true);
    },
  );
  it("identifies the filtered campaign and never labels a demo outcome as a real policy", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: {
        campaign_filter: {
          reference: "01CAMPAIGN",
          title: "AUI demo",
          endpoint_slug: "aui-unique",
        },
        lifecycles: [
          {
            ...lifecycle,
            policy: { ...lifecycle.policy, result_code: "policy_issued_demo" },
          },
        ],
      },
    });
    expect(
      wrapper.get('[data-testid="campaign-policy-lifecycle-filter"]').text(),
    ).toContain("aui-unique");
    expect(wrapper.text()).toContain("Demo summary ready");
    expect(wrapper.text()).toContain("not an issued insurance policy");
    expect(wrapper.text()).not.toContain("Policy completed");
  });
  it("renders a coherent read-only lifecycle without private or mutation surfaces", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: { lifecycles: [lifecycle] },
    });

    expect(wrapper.text()).toContain("AUI On-Demand Insurance");
    expect(wrapper.text()).toContain("Policy completed");
    expect(wrapper.text()).toContain("₱122.00");
    expect(wrapper.text()).toContain("Personal Accident");
    expect(wrapper.text()).toContain("₱1,000.00");
    expect(wrapper.text()).toContain("Provisional");
    expect(wrapper.text()).toContain("AUI-ABCD");
    expect(wrapper.text()).toContain("View completion Pay Code");
    expect(wrapper.text()).toContain("Succeeded");
    expect(wrapper.text()).toContain("Accepted");
    expect(wrapper.find("h2").text()).toBe("AUI On-Demand Insurance");
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
    expect(wrapper.text()).toContain("Declined");
    expect(wrapper.text()).toContain("Not issued");
    expect(wrapper.text()).toContain("Claim not completed");
    expect(wrapper.text()).toContain("Completion time unavailable");
    expect(
      wrapper
        .find('[data-testid="campaign-policy-lifecycle-completion-link"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="campaign-policy-lifecycle-attention"]')
        .exists(),
    ).toBe(true);
  });

  it("links an issued completion Pay Code to its authoritative read-only detail route", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: { lifecycles: [lifecycle] },
    });
    const link = wrapper.get(
      '[data-testid="campaign-policy-lifecycle-completion-link"]',
    );

    expect(link.attributes("href")).toBe("/x/cockpit/pay-codes/AUI-ABCD");
    expect(link.attributes("aria-label")).toBe(
      "View completion Pay Code AUI-ABCD",
    );
  });

  it.each([
    ["provisional_coverage_active", "Provisional coverage active"],
    ["awaiting_completion_claim", "Awaiting completion claim"],
    ["claim_evidence_ready", "Claim evidence ready"],
    ["policy_awaiting_approval", "Awaiting checker approval"],
    ["policy_authorized", "Policy completion authorized"],
    ["policy_succeeded", "Policy completed"],
    ["policy_failed", "Policy completion failed"],
    ["policy_indeterminate", "Policy outcome indeterminate"],
  ])("renders the %s lifecycle stage coherently", (stage, expected) => {
    const attentionRequired = [
      "policy_failed",
      "policy_indeterminate",
    ].includes(stage);
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: {
        lifecycles: [
          {
            ...lifecycle,
            stage,
            attention_required: attentionRequired,
          },
        ],
      },
    });

    expect(wrapper.text()).toContain(expected);
    expect(wrapper.text()).toContain("Policy lifecycle");
    expect(
      wrapper.find('[data-testid="campaign-policy-lifecycle-guidance"]').text(),
    ).not.toBe("");
    expect(
      wrapper
        .find('[data-testid="campaign-policy-lifecycle-attention"]')
        .exists(),
    ).toBe(attentionRequired);
  });

  it("keeps long safe values inside responsive containers", () => {
    const longValue = "very-long-safe-lifecycle-value-".repeat(12);
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: {
        lifecycles: [
          {
            ...lifecycle,
            stage: longValue,
            campaign: {
              reference: longValue,
              name: longValue,
              revision_id: longValue,
            },
            coverage: {
              ...lifecycle.coverage,
              type: longValue,
              status: longValue,
            },
            policy: {
              ...lifecycle.policy,
              status: longValue,
              result_code: longValue,
            },
          },
        ],
      },
    });

    expect(
      wrapper.get('[data-testid="campaign-policy-lifecycle-stage"]').classes(),
    ).toEqual(
      expect.arrayContaining([
        "max-w-full",
        "whitespace-normal",
        "break-words",
      ]),
    );
    expect(wrapper.findAll(".min-w-0").length).toBeGreaterThan(1);
  });

  it("uses contextual language for nullable intermediate facts", () => {
    const wrapper = mount(CampaignPolicyLifecycle, {
      props: {
        lifecycles: [
          {
            ...lifecycle,
            payment: { ...lifecycle.payment, settled_at: null },
            coverage: {
              ...lifecycle.coverage,
              effective_at: null,
              expires_at: null,
            },
            completion: null,
            policy: null,
            updated_at: null,
          },
        ],
      },
    });

    expect(wrapper.text()).toContain("Settlement pending");
    expect(wrapper.text()).toContain("Effective time unavailable");
    expect(wrapper.text()).toContain("No expiry recorded");
    expect(wrapper.text()).toContain("No policy request");
    expect(wrapper.text()).toContain("Update time unavailable");
    expect(wrapper.text()).not.toContain("Not yet");
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
