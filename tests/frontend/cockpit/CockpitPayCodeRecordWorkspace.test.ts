import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import CockpitPayCodeRecordWorkspace from "../../../resources/js/cockpit/components/CockpitPayCodeRecordWorkspace.vue";

const voucher = {
  code: "CAMP-CB2L",
  status: "redeemed",
  authorized: true,
  summary: {},
  overview: {
    capability: { label: "Disbursement" },
    party: { label: "Claimed By", primary: "•••• 1987" },
    amounts: [
      {
        key: "reserved_principal",
        label: "Reserved Principal",
        amount_minor: 2_000,
        currency: "PHP",
        authority: "treasury_position",
        primary: true,
      },
      {
        key: "paid_amount",
        label: "Paid Amount",
        amount_minor: 2_000,
        currency: "PHP",
        authority: "voucher_claims",
        primary: false,
      },
    ],
    timing: {
      issued_at: "2026-08-03T08:00:00+08:00",
      redeemed_at: "2026-08-03T08:15:00+08:00",
    },
  },
  instructions: {
    groups: [
      {
        key: "claim",
        label: "Claim Requirements",
        facts: [{ label: "Required Inputs", value: "Mobile, Signature" }],
      },
    ],
  },
  claims: {
    records: [
      {
        id: 1,
        claim_number: 1,
        status: "paid",
        disbursed_amount_minor: 2_000,
        currency: "PHP",
        claimer_mobile_masked: "•••• 1987",
        attempted_at: "2026-08-03T08:15:00+08:00",
        completed_at: "2026-08-03T08:19:00+08:00",
      },
    ],
    evidence: [
      {
        id: 44,
        key: "signature",
        label: "Signature",
        kind: "image",
        status: "captured",
        value: null,
        revealable: true,
        reveal_href: "/x/cockpit/pay-codes/CAMP-CB2L/evidence/input/44",
      },
    ],
  },
  settlement: {
    envelope: {
      available: true,
      reference: "ENV-001",
      driver: "campaign",
      driver_version: "1",
      status: "approved",
      settleable: true,
      checklist: { required_count: 2, required_completed: 2 },
      gates: [],
    },
  },
  treasury: {
    backing: {
      mode: "treasury_position",
      label: "Treasury Backing",
      status: "settled",
    },
  },
  evidence_summary: [],
  distribution_links: {},
  redactions: {},
};

describe("Cockpit Pay Code record workspace", () => {
  it("keeps authoritative value and accounting backing visually primary", () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    expect(
      wrapper.get('[data-testid="pay-code-record-workspace"]').text(),
    ).toContain("₱20.00");
    expect(
      wrapper.get('[data-testid="pay-code-overview-tab"]').text(),
    ).toContain("Treasury Backing");
    expect(
      wrapper.get('[data-testid="pay-code-overview-tab"]').text(),
    ).toContain("Authority: Treasury Position");
    expect(
      wrapper.get('[data-testid="pay-code-overview-tab"]').text(),
    ).toContain("A legacy Cash entity is not the monetary authority.");
  });

  it("navigates the record without loading protected evidence until reveal", async () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    const claimTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Claim & Evidence"));
    expect(claimTab).toBeDefined();
    await claimTab!.trigger("click");

    expect(wrapper.get('[data-testid="pay-code-claim-tab"]').text()).toContain(
      "Access is recorded",
    );
    expect(wrapper.find('img[src*="/evidence/input/44"]').exists()).toBe(false);

    const reveal = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Reveal Signature"));
    expect(reveal).toBeDefined();
    await reveal!.trigger("click");

    expect(
      wrapper
        .get('img[src="/x/cockpit/pay-codes/CAMP-CB2L/evidence/input/44"]')
        .exists(),
    ).toBe(true);
  });

  it("shows settlement readiness separately from monetary backing", async () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    const settlementTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Settlement"));
    await settlementTab!.trigger("click");

    const settlement = wrapper
      .get('[data-testid="pay-code-settlement-tab"]')
      .text();
    expect(settlement).toContain("Required evidence");
    expect(settlement).toContain("100%");
    expect(settlement).toContain(
      "The Settlement Envelope proves readiness. Treasury Backing proves where the money is held.",
    );
  });

  it("distinguishes lifecycle closure from payout completion", async () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    const overview = wrapper
      .get('[data-testid="pay-code-overview-tab"]')
      .text();
    expect(overview).toContain("Voucher Closed");
    expect(overview).toContain(
      "Voucher Closed marks the lifecycle transition. Payout completion is recorded separately under Claim & Evidence.",
    );

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Claim & Evidence"))!
      .trigger("click");

    expect(wrapper.get('[data-testid="pay-code-claim-tab"]').text()).toContain(
      "Payout completed",
    );
  });

  it("separates a recorded claim from a rejected payout and offers same-code correction", async () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "LM52",
        status: "redeemed",
        voucher,
        redemption: {
          status: "failed",
          claim_status: "payout_rejected",
          payout_status: "failed",
          amount_minor: 100_000,
          currency: "PHP",
          bank_code: "GXCHPHM2XXX",
          account_number_masked: "*******6025",
          rejection_reason: "AC01 (Incorrect account number)",
          requires_recovery: true,
          can_correct_destination: true,
        },
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    const overviewWarning = wrapper.get(
      '[data-testid="pay-code-overview-payout-warning"]',
    );
    expect(overviewWarning.text()).toContain(
      "Claim completed · Payout needs correction",
    );
    expect(overviewWarning.text()).toContain(
      "AC01 (Incorrect account number)",
    );
    expect(overviewWarning.text()).toContain(
      "principal remains protected",
    );

    await wrapper
      .get('[data-testid="pay-code-overview-review-correction"]')
      .trigger("click");

    const rejection = wrapper.get('[data-testid="pay-code-payout-rejected"]');
    expect(rejection.text()).toContain("Claim recorded · Payout rejected");
    expect(rejection.text()).toContain("AC01 (Incorrect account number)");
    expect(rejection.text()).toContain("principal remains protected");
    expect(rejection.text()).toContain("receiving institution still makes the final decision");
    expect(wrapper.get('[data-testid="pay-code-payout-correction-form"]').attributes('action'))
      .toBe('/x/cockpit/pay-codes/LM52/payout-corrections');
  });

  it("shows exact journal and delivery evidence with its delivery time", async () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        journal: {
          status: "available",
          authorized: true,
          entries: [
            {
              reference_number: "ERN-001",
              event_type: "treasury.pay_code.disbursement.settled",
              occurred_at: "2026-08-03T08:19:00+08:00",
            },
          ],
        },
        feedback: {
          status: "available",
          authorized: true,
          deliveries: [
            {
              delivery_id: "delivery-001",
              channel: "sms",
              status: "sent",
              provider_status: "ACCEPTED",
              delivered_at: "2026-08-03T08:01:00+08:00",
            },
          ],
        },
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Audit"))!
      .trigger("click");

    const audit = wrapper.get('[data-testid="pay-code-audit-tab"]').text();
    expect(audit).toContain("Treasury Pay Code Disbursement Settled");
    expect(audit).toContain("Sms");
    expect(audit).toContain("Accepted");
    expect(audit).toContain("Delivered");
  });
});
