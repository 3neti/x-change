import { mount } from "@vue/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import VoucherDetail from "../../../resources/js/cockpit/pages/VoucherDetail.vue";
import VoucherDetailRouteAdapter from "../../../resources/js/pages/x-change/cockpit/VoucherDetail.vue";

const readModel = {
  code: "PC-HYDRATED-001",
  voucher: {
    code: "PC-HYDRATED-001",
    status: "issued",
    authorized: true,
    summary: {
      code: "PC-HYDRATED-001",
      status: "issued",
      display_status: "ready",
      amount: 1500.75,
      currency: "PHP",
      claimed: false,
      fully_claimed: false,
      provider_payload: "must-not-render",
      raw_payload: "must-not-render",
      wallet: "must-not-render",
    },
    overview: {
      capability: { label: "Disbursement" },
      party: { label: "Recipient", primary: "Open claim" },
      amounts: [
        {
          key: "reserved_principal",
          label: "Pay Code Value",
          amount_minor: 150075,
          currency: "PHP",
          authority: "treasury_position",
          primary: true,
        },
      ],
      timing: {
        issued_at: "2026-07-03T10:00:00+08:00",
        starts_at: "2026-07-03T11:00:00+08:00",
        expires_at: "2026-07-10T11:00:00+08:00",
        redeemed_at: null,
      },
    },
    instructions: {
      groups: [
        {
          key: "claim",
          label: "Claim Requirements",
          facts: [
            { label: "Required Inputs", value: "Mobile, OTP, Selfie" },
            { label: "Validations", value: "Mobile, OTP" },
            { label: "Target Mobile", value: "•••• 1987" },
            { label: "Vendor", value: "GCash" },
          ],
        },
        {
          key: "experience",
          label: "Recipient Experience",
          facts: [{ label: "Message", value: "Bring a valid ID" }],
        },
      ],
    },
    claims: { records: [], evidence: [] },
    settlement: { envelope: { available: false } },
    treasury: {
      backing: {
        mode: "treasury_position",
        label: "Treasury Backing",
        status: "settled",
      },
    },
    pos_reference: {
      schema: "x-change.cockpit.pos-sale-reference.v1",
      sale_reference: "POS-20260828-01HZZZZZZZZZZZZZZZZZZZZZZZ",
      order_reference: "ORDER-42",
      purpose: "Snacks",
      legacy_reference: null,
      reference_kind: "canonical",
    },
    evidence_summary: [],
    distribution_links: {
      status: "available",
      available: true,
      read_only: true,
      redeem_url: "https://example.test/x/claim/PC-HYDRATED-001/experience",
      redeem_path: "/x/claim/PC-HYDRATED-001/experience",
      claim_qr: "data:image/png;base64,FAKE-HYDRATED-QR",
      redactions: { payloads: "distribution-links-only" },
    },
    redactions: {
      payloads: "sanitized-summary-only",
      excluded: ["provider_payload", "raw_payload", "wallet"],
    },
  },
  execution: { status: "not_wired", events: [], authorized: false },
  journal: { status: "not_wired", entries: [], authorized: false },
  actions: { status: "not_wired", actions: [], authorized: false },
  feedback: { status: "not_wired", deliveries: [], authorized: false },
};

const terminalControl = {
  schema: "x-change.cockpit.pay-code-terminal-control.v1",
  authorized: true,
  status: "available",
  can_expire: true,
  can_cancel: true,
  blocked_reason: null,
  release: {
    amount_minor: 150075,
    currency: "PHP",
    from: "Pay Code Reserve",
    to: "Client Funds",
    provider_inventory_changed: false,
    provider_calls: false,
    issuance_charges_refunded: false,
  },
  history: [],
};

describe("Cockpit Voucher Detail hydration", () => {
  afterEach(() => {
    window.history.replaceState({}, "", "/");
    vi.unstubAllGlobals();
  });

  it("hydrates the real visible Pay Code workspace from sanitized facts", () => {
    const wrapper = mount(VoucherDetail, {
      props: {
        context: { code: "PC-HYDRATED-001" },
        read_model: readModel,
      },
    });

    expect(
      wrapper.get('[data-testid="cockpit-voucher-detail-header"]').text(),
    ).toContain("PC-HYDRATED-001");
    expect(
      wrapper.get('[data-testid="pay-code-record-workspace"]').text(),
    ).toContain("₱1,500.75");
    expect(
      wrapper.get('[data-testid="pay-code-record-workspace"]').text(),
    ).toContain("Open claim");
    expect(
      wrapper.get('[data-testid="cockpit-pay-code-share-card"]').text(),
    ).toContain("PC-HYDRATED-001");
    expect(wrapper.text()).toContain("sanitized-summary-only");
    expect(
      wrapper
        .find('[data-testid="cockpit-voucher-detail-legacy-projection"]')
        .exists(),
    ).toBe(false);
    expect(wrapper.text()).not.toContain("must-not-render");
    expect(wrapper.text()).not.toContain("provider_payload");
    expect(wrapper.text()).not.toContain("raw_payload");
    expect(
      wrapper.get('[data-testid="pay-code-overview-pos-reference"]').text(),
    ).toContain("POS-20260828-01HZZZZZZZZZZZZZZZZZZZZZZZ");
    expect(wrapper.get('[data-testid="pay-code-overview-pos-reference"]').text()).toContain("ORDER-42");
    expect(wrapper.get('[data-testid="pay-code-overview-pos-reference"]').text()).toContain("Snacks");
  });

  it("prefers canonical consumer status on collectible detail", () => {
    const wrapper = mount(VoucherDetail, {
      props: {
        context: { code: "PC-HYDRATED-001" },
        read_model: {
          ...readModel,
          voucher: {
            ...readModel.voucher,
            collection: {
              schema: "x-change.cockpit.pay-code-collection.v1",
              consumer_status: "processing",
              currency: "PHP",
              target_amount_minor: 150075,
              collected_total_minor: 50000,
              remaining_to_collect_minor: 100075,
              is_fully_collected: false,
              is_overpaid: false,
              overpaid_amount_minor: 0,
            },
          },
        },
      },
    });

    expect(wrapper.get('[data-testid="pay-code-record-workspace"]').text()).toContain(
      "Processing",
    );
    expect(
      wrapper.get('[data-testid="pay-code-overview-collection-progress"]').text(),
    ).toContain("₱1,000.75");
  });

  it("labels a historical POS reference without fabricating a canonical sale reference", () => {
    const wrapper = mount(VoucherDetail, {
      props: {
        context: { code: "PC-HYDRATED-001" },
        read_model: {
          ...readModel,
          voucher: {
            ...readModel.voucher,
            pos_reference: {
              schema: "x-change.cockpit.pos-sale-reference.v1",
              sale_reference: null,
              order_reference: null,
              purpose: null,
              legacy_reference: "OLD-POS-SNACKS",
              reference_kind: "legacy",
            },
          },
        },
      },
    });

    const reference = wrapper.get(
      '[data-testid="pay-code-overview-pos-reference"]',
    );

    expect(reference.text()).toContain("Legacy POS reference");
    expect(reference.text()).toContain("OLD-POS-SNACKS");
    expect(reference.text()).not.toContain("POS-20260828");
  });

  it("leads Overview with plain-language claim readiness", () => {
    const wrapper = mount(VoucherDetail, {
      props: { read_model: readModel },
    });
    const card = wrapper.get('[data-testid="pay-code-overview-backing-card"]');
    const details = wrapper.get(
      '[data-testid="pay-code-overview-backing-details"]',
    );

    expect(card.text()).toContain("Claim readiness");
    expect(card.text()).toContain("Value is safely held and ready");
    expect(card.text()).toContain("governed account structure");
    expect(details.element.tagName).toBe("DETAILS");
    expect(details.attributes("open")).toBeUndefined();
    expect(details.text()).toContain("Treasury Backing");
    expect(details.text()).toContain("Treasury Position");
    expect(details.text()).toContain("Settled");
  });

  it("renders claimant-facing instructions from the immutable snapshot", async () => {
    const wrapper = mount(VoucherDetail, {
      props: { read_model: readModel },
    });

    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes("Instructions"))!
      .trigger("click");

    const instructions = wrapper.get(
      '[data-testid="pay-code-instructions-tab"]',
    );

    expect(instructions.text()).toContain("Immutable Snapshot");
    expect(instructions.text()).toContain(
      "A plain-language guide to what the claimant will provide, verify, and see.",
    );
    expect(instructions.text()).toContain(
      "The claimant must verify their mobile number, enter the one-time code sent to that mobile and provide a selfie for identity verification.",
    );
    expect(instructions.text()).toContain(
      "The claim verifies Mobile and OTP before it can continue.",
    );
    expect(instructions.text()).toContain(
      "Only the verified mobile number ending in 1987 may claim this Pay Code.",
    );
    expect(instructions.text()).toContain(
      "The claimant must provide a GCash payout account.",
    );
    expect(instructions.text()).toContain(
      "The claimant will see: “Bring a valid ID”",
    );
    expect(instructions.text()).not.toContain("Required Inputs");
    expect(instructions.text()).not.toContain("Target Mobile");
  });

  it("frames terminal controls as management of this Pay Code without changing their forms", () => {
    const wrapper = mount(VoucherDetail, {
      props: {
        read_model: readModel,
        terminal_control: terminalControl,
      },
    });
    const boundary = wrapper.get(
      '[data-testid="pay-code-overview-actions-boundary"]',
    );
    const controls = wrapper.get('[data-testid="pay-code-terminal-controls"]');

    expect(boundary.text()).toContain("Actions for this Pay Code");
    expect(controls.text()).toContain("Manage this Pay Code");
    expect(controls.text()).toContain("Principal Releasable");
    expect(controls.text()).toContain("Pay Code Reserve");
    expect(controls.text()).toContain("Client Funds · ₱1,500.75");
    expect(
      wrapper.get('[data-testid="pay-code-expire-form"]').attributes("action"),
    ).toBe("/x/cockpit/pay-codes/PC-HYDRATED-001/terminal-actions");
    expect(
      wrapper.get('[data-testid="pay-code-cancel-form"]').attributes("action"),
    ).toBe("/x/cockpit/pay-codes/PC-HYDRATED-001/terminal-actions");
  });

  it("keeps the live navigation and tab surfaces available without legacy panels", () => {
    const wrapper = mount(VoucherDetail, {
      props: { read_model: readModel },
    });
    const workspace = wrapper.get('[data-testid="pay-code-record-workspace"]');

    expect(workspace.text()).toContain("Overview");
    expect(workspace.text()).toContain("Instructions");
    expect(workspace.text()).toContain("Claim & Evidence");
    expect(workspace.text()).toContain("Settlement");
    expect(workspace.text()).toContain("Audit");
    expect(workspace.text()).toContain("Engineering");
    expect(workspace.text()).toContain("Pay Codes");
    expect(workspace.text()).toContain("Distribution");
    expect(
      wrapper
        .find('[data-testid="cockpit-voucher-detail-primary-summary"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-voucher-detail-distribution-links-panel"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-voucher-secondary-content"]')
        .exists(),
    ).toBe(false);
  });

  it("forwards Inertia route adapter props into the visible detail workspace", () => {
    const wrapper = mount(VoucherDetailRouteAdapter, {
      props: {
        context: { code: "PC-HYDRATED-001" },
        read_model: readModel,
        terminal_control: terminalControl,
      },
    });

    expect(wrapper.text()).toContain("PC-HYDRATED-001");
    expect(wrapper.text()).toContain("₱1,500.75");
    expect(
      wrapper.find('[data-testid="cockpit-voucher-detail-shell"]').exists(),
    ).toBe(true);
    expect(
      wrapper.find('[data-testid="pay-code-record-workspace"]').exists(),
    ).toBe(true);
    expect(
      wrapper
        .find('[data-testid="cockpit-voucher-detail-legacy-projection"]')
        .exists(),
    ).toBe(false);
  });
});
