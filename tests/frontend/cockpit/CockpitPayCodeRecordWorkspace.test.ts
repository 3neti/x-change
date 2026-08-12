import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import BankEMISelect from "../../../resources/js/components/x-change-shared-financial/BankEMISelect.vue";
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
        evidence: {
          required_count: 1,
          captured_count: 1,
          complete: true,
          manifest_version: 1,
        },
      },
    ],
    evidence: [
      {
        id: 44,
        key: "signature",
        label: "Signature",
        group: "media",
        kind: "image",
        status: "captured",
        value: null,
        revealable: true,
        reveal_href: "/x/cockpit/pay-codes/CAMP-CB2L/evidence/input/44",
        claim_number: 1,
        legacy: false,
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
  afterEach(() => {
    window.history.replaceState({}, "", "/");
    vi.unstubAllGlobals();
  });

  it("loads the sanitized Engineering Preview only when its tab is opened", async () => {
    const fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        schema: "x-change.cockpit.pay-code-engineering-preview.v1",
        pay_code: { code: "CAMP-CB2L" },
        redactions: { binary_evidence: "excluded" },
      }),
    });
    vi.stubGlobal("fetch", fetch);

    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    expect(fetch).not.toHaveBeenCalled();

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Engineering"))!
      .trigger("click");
    await flushPromises();

    expect(fetch).toHaveBeenCalledOnce();
    expect(fetch).toHaveBeenCalledWith(
      "/x/cockpit/pay-codes/CAMP-CB2L/engineering-preview",
      expect.objectContaining({ credentials: "same-origin" }),
    );
    expect(wrapper.get('[data-testid="engineering-preview-json"]').text())
      .toContain("x-change.cockpit.pay-code-engineering-preview.v1");
    expect(wrapper.get('[data-testid="engineering-preview-json"]').text())
      .toContain('"binary_evidence": "excluded"');
  });

  it("opens and focuses the claim identified by an authenticated feedback link", () => {
    window.history.replaceState(
      {},
      "",
      "/x/cockpit/pay-codes/CAMP-CB2L?tab=claim&claim=1#claim-1",
    );

    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    expect(wrapper.get('[data-testid="pay-code-claim-tab"]').exists()).toBe(
      true,
    );
    expect(wrapper.get("#claim-1").attributes("data-focused")).toBe("true");
  });

  it("falls back to the overview for an unknown feedback tab", () => {
    window.history.replaceState(
      {},
      "",
      "/x/cockpit/pay-codes/CAMP-CB2L?tab=secrets&claim=1",
    );

    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    expect(wrapper.get('[data-testid="pay-code-overview-tab"]').exists()).toBe(
      true,
    );
  });

  it("shows the Treasury-safe terminal impact before an owner acts", () => {
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "OPEN-200",
        status: "active",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
        terminalControl: {
          schema: "x-change.cockpit.pay-code-terminal-control.v1",
          authorized: true,
          status: "available",
          can_expire: true,
          can_cancel: true,
          blocked_reason: null,
          release: {
            amount_minor: 20_000,
            currency: "PHP",
            from: "Pay Code Reserve",
            to: "Client Funds",
            provider_inventory_changed: false,
            provider_calls: false,
            issuance_charges_refunded: false,
          },
          history: [],
        },
      },
    });

    const controls = wrapper.get('[data-testid="pay-code-terminal-controls"]');

    expect(controls.text()).toContain("Treasury-safe terminal actions");
    expect(controls.text()).toContain("Pay Code Reserve");
    expect(controls.text()).toContain("Client Funds · ₱200.00");
    expect(controls.text()).toContain("Provider Inventory unchanged");
    expect(controls.text()).toContain("No provider call");
    expect(controls.text()).toContain("Issuance charges retained");
    expect(
      wrapper.get('[data-testid="pay-code-expire-form"]').attributes("action"),
    ).toBe("/x/cockpit/pay-codes/OPEN-200/terminal-actions");
    expect(
      wrapper.get('[data-testid="pay-code-cancel-form"]').attributes("action"),
    ).toBe("/x/cockpit/pay-codes/OPEN-200/terminal-actions");
  });

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
    expect(
      wrapper.get('[data-testid="claim-evidence-coverage"]').text(),
    ).toContain("1 of 1 required items captured");
    expect(wrapper.get('[data-testid="pay-code-claim-tab"]').text()).toContain(
      "Selfie & Signature",
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

  it("explains retained evidence whose private artifact is unavailable", async () => {
    const unavailableVoucher = structuredClone(voucher);
    unavailableVoucher.claims.evidence[0] = {
      ...unavailableVoucher.claims.evidence[0],
      artifact_status: "missing",
      revealable: false,
      reveal_href: null,
    };
    const wrapper = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "F6BG",
        status: "redeemed",
        voucher: unavailableVoucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Claim & Evidence"))!
      .trigger("click");

    const claimTab = wrapper.get('[data-testid="pay-code-claim-tab"]');
    expect(claimTab.text()).toContain(
      "Private file unavailable. Its captured evidence record and summary remain retained.",
    );
    expect(
      wrapper
        .findAll("button")
        .some((button) => button.text().includes("Reveal Signature")),
    ).toBe(false);
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
        payoutInstitutions: [
          { key: "gcash", value: "GXCHPHM2XXX", name: "GCash", short_name: "GCash", category: "wallet", account_label: "GCash Mobile Number", identifier_scheme: "ph_mobile", aliases: ["G-Xchange"], commonly_used: true },
          { key: "pnb", value: "PNBMPHMMTOD", name: "Philippine National Bank", short_name: "PNB", category: "bank", account_label: "Account Number", identifier_scheme: "account_number", aliases: ["PNB"], commonly_used: true },
        ],
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
    expect(overviewWarning.text()).toContain("AC01 (Incorrect account number)");
    expect(overviewWarning.text()).toContain("principal remains protected");

    await wrapper
      .get('[data-testid="pay-code-overview-review-correction"]')
      .trigger("click");

    const rejection = wrapper.get('[data-testid="pay-code-payout-rejected"]');
    expect(rejection.text()).toContain("Claim recorded · Payout rejected");
    expect(rejection.text()).toContain("AC01 (Incorrect account number)");
    expect(rejection.text()).toContain("principal remains protected");
    expect(rejection.text()).toContain(
      "receiving institution still makes the final decision",
    );
    expect(
      wrapper
        .get('[data-testid="pay-code-payout-correction-form"]')
        .attributes("action"),
    ).toBe("/x/cockpit/pay-codes/LM52/payout-corrections");
    const selector = wrapper.getComponent(BankEMISelect);
    expect(selector.props("institutions")).toHaveLength(2);
    expect(selector.props("institutions")[1].name).toBe(
      "Philippine National Bank",
    );
    expect(
      wrapper
        .get('[data-testid="pay-code-payout-correction-form"]')
        .find('input[name="bank_code"]')
        .attributes("type"),
    ).toBe("hidden");
    expect(rejection.text()).not.toContain("PNBMPHMMTOD");
  });

  it("places the prominent share card near the beginning of the Overview tab only when a canonical claim URL is available", () => {
    const claimable = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "active",
        voucher,
        claimUrl: "https://example.test/x/claim/CAMP-CB2L",
        claimQr: "data:image/png;base64,FAKE-QR",
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    const overview = claimable.get('[data-testid="pay-code-overview-tab"]');
    const shareCard = overview.get(
      '[data-testid="cockpit-pay-code-share-card"]',
    );

    expect(shareCard.attributes("data-variant")).toBe("prominent");
    expect(
      overview.get('[data-testid="cockpit-pay-code-share-code"]').text(),
    ).toBe("|| CAMP-CB2L ||");
    // Prominent and near the beginning: it is the first element in the tab.
    expect(overview.element.firstElementChild).toBe(shareCard.element);

    const nonClaimable = mount(CockpitPayCodeRecordWorkspace, {
      props: {
        code: "CAMP-CB2L",
        status: "redeemed",
        voucher,
        distributionUrl: "/distribution",
        explorerUrl: "/pay-codes",
      },
    });

    expect(
      nonClaimable
        .find('[data-testid="cockpit-pay-code-share-card"]')
        .exists(),
    ).toBe(false);
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
