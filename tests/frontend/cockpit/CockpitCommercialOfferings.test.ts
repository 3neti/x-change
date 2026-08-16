import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CommercialOfferings from "../../../resources/js/cockpit/pages/CommercialOfferings.vue";

vi.mock("@inertiajs/vue3", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@inertiajs/vue3")>()),
  Head: { template: "<div><slot /></div>" },
}));
vi.mock("@/routes/x-change/cockpit/commercial/partners", () => ({
  store: Object.assign(() => "/x/cockpit/commercial/partners", {
    url: () => "/x/cockpit/commercial/partners",
  }),
}));
vi.mock(
  "@/routes/x-change/cockpit/commercial/partner_revisions/approvals",
  () => ({
    store: Object.assign(
      (id: number) => `/x/cockpit/commercial/partner-revisions/${id}/approvals`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/partner-revisions/${id}/approvals`,
      },
    ),
  }),
);
vi.mock("@/routes/x-change/cockpit/commercial/partners/destinations", () => ({
  store: Object.assign(
    (id: number) => `/x/cockpit/commercial/partners/${id}/destinations`,
    {
      url: (id: number) => `/x/cockpit/commercial/partners/${id}/destinations`,
    },
  ),
}));
vi.mock(
  "@/routes/x-change/cockpit/commercial/partner_destination_revisions/approvals",
  () => ({
    store: Object.assign(
      (id: number) =>
        `/x/cockpit/commercial/partner-destination-revisions/${id}/approvals`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/partner-destination-revisions/${id}/approvals`,
      },
    ),
  }),
);
vi.mock("@/routes/x-change/cockpit/commercial/provider_cost_batches", () => ({
  store: Object.assign(() => "/x/cockpit/commercial/provider-cost-batches", {
    url: () => "/x/cockpit/commercial/provider-cost-batches",
  }),
}));
vi.mock(
  "@/routes/x-change/cockpit/commercial/commission_payout_batches",
  () => ({
    store: Object.assign(
      () => "/x/cockpit/commercial/commission-payout-batches",
      { url: () => "/x/cockpit/commercial/commission-payout-batches" },
    ),
  }),
);
vi.mock(
  "@/routes/x-change/cockpit/commercial/commission_payout_batches/approvals",
  () => ({
    store: Object.assign(
      (id: number) =>
        `/x/cockpit/commercial/commission-payout-batches/${id}/approvals`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/commission-payout-batches/${id}/approvals`,
      },
    ),
  }),
);
vi.mock(
  "@/routes/x-change/cockpit/commercial/commission_payout_batches/submissions",
  () => ({
    store: Object.assign(
      (id: number) =>
        `/x/cockpit/commercial/commission-payout-batches/${id}/submissions`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/commission-payout-batches/${id}/submissions`,
      },
    ),
  }),
);
vi.mock(
  "@/routes/x-change/cockpit/commercial/commission_payout_batches/reconciliations",
  () => ({
    store: Object.assign(
      (id: number) =>
        `/x/cockpit/commercial/commission-payout-batches/${id}/reconciliations`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/commission-payout-batches/${id}/reconciliations`,
      },
    ),
  }),
);
vi.mock(
  "@/routes/x-change/cockpit/commercial/commission_payout_batches/retries",
  () => ({
    store: Object.assign(
      (id: number) =>
        `/x/cockpit/commercial/commission-payout-batches/${id}/retries`,
      {
        url: (id: number) =>
          `/x/cockpit/commercial/commission-payout-batches/${id}/retries`,
      },
    ),
  }),
);

const active = {
  reference: "commercial-offering:pay_code",
  version: 1,
  effective_at: "2026-08-07T00:00:00+00:00",
  catalog: {
    reference: "pay-code",
    version: 3,
    currency: "PHP",
    items: [
      {
        reference: "cash.amount",
        label: "Transaction Fee",
        category: "base",
        currency: "PHP",
        unit_price_minor: 1500,
      },
      {
        reference: "inputs.fields.otp",
        label: "OTP Verification",
        category: "input_fields",
        currency: "PHP",
        unit_price_minor: 200,
      },
    ],
  },
  waterfall_policy: {
    reference: "pay-code-commercial-waterfall",
    version: 1,
    currency: "PHP",
    rules: [
      {
        reference: "provider-transfer-cost",
        sequence: 10,
        line_type: "deduction" as const,
        category: "provider_cost",
        recipient_reference: "provider:settlement-rail",
        fixed_amount_minor: 1000,
        basis_points: null,
        minimum_amount_minor: null,
        maximum_amount_minor: null,
        participant_role: null,
      },
      {
        reference: "commercial-residual",
        sequence: 40,
        line_type: "residual" as const,
        category: "commercial_revenue",
        recipient_reference: "operator:x-change",
        fixed_amount_minor: null,
        basis_points: null,
        minimum_amount_minor: null,
        maximum_amount_minor: null,
        participant_role: null,
      },
    ],
  },
  legal_trace: {
    jurisdiction: "PH",
    profile: "treasury-settlement-ph-v1",
    decision: "advisory_review_required",
  },
};

const artifact = {
  schema: "3neti.x-change.commercial-offering-manifest.v1",
  hash: "c".repeat(64),
  yaml: "schema: 3neti.x-change.commercial-offering-manifest.v1\nprofile: pay_code\n",
  snapshot_hash: "d".repeat(64),
  activation_reference: "commercial-baseline:pay_code:test",
  activated_at: "2026-08-07T00:00:00+00:00",
};

const history = [
  {
    reference: "commercial-offering:pay_code",
    version: 1,
    status: "published",
    origin: "installation_baseline",
    snapshot_hash: artifact.snapshot_hash,
    manifest_hash: artifact.hash,
    effective_at: "2026-08-07T00:00:00+00:00",
    approved_at: null,
  },
];

const controls = {
  schema: "x-change.cockpit.commercial-controls.v1",
  sales: {
    count: 1,
    posted_count: 1,
    reversed_count: 0,
    total_charged_minor: 2500,
    currency: "PHP",
  },
  allocation_totals: [
    {
      category: "product_revenue",
      currency: "PHP",
      amount_minor: 300,
      allocation_count: 1,
    },
  ],
  position_balances: [
    {
      purpose: "product_revenue",
      category: "product_revenue",
      currency: "PHP",
      current_minor: 300,
      lifetime_allocated_minor: 500,
      settled_minor: 200,
      remaining_minor: 300,
      difference_minor: 0,
      reconciled: true,
    },
  ],
  provider_costs: {
    settled_count: 1,
    settled_minor: 1000,
    variance_minor: 0,
    outstanding_minor: 200,
    recent_batches: [
      {
        reference: "provider-cost:2026-08",
        provider: "netbank",
        connection_reference: "netbank-primary",
        currency: "PHP",
        expected_amount_minor: 1000,
        observed_amount_minor: 900,
        variance_amount_minor: -100,
        status: "review_required",
        observed_at: "2026-08-08T00:00:00+00:00",
      },
    ],
  },
  commissions: {
    earned_minor: 100,
    requested_minor: 0,
    settled_minor: 0,
    open_count: 0,
    available_minor: 100,
    recent_batches: [
      {
        id: 9,
        reference: "commission:partner:acceptance:2026-08",
        commercial_partner_id: 1,
        destination_revision_id: 1,
        partner_reference: "partner:acceptance",
        provider: "netbank",
        connection_reference: "netbank-primary",
        destination_summary: "GCash · ••••4567",
        amount_minor: 100,
        currency: "PHP",
        status: "awaiting_approval",
        requested_at: "2026-08-08T00:00:00+00:00",
        settled_at: null,
        attempt_count: 0,
        last_attempt: null,
      },
    ],
  },
  operations: {
    live_provider_calls_enabled: false,
    connections: [
      {
        reference: "netbank-primary",
        provider: "netbank",
        currency: "PHP",
      },
    ],
  },
  recent_sales: [],
  policy: {
    attribution: {
      reference: "commercial-attribution:pay-code",
      version: 1,
      eligible_roles: [
        "originating_partner",
        "sales_partner",
        "marketing_partner",
      ],
      unattributed_commission_behavior: "skip_to_residual",
    },
    legal_trace: {
      jurisdiction: "PH",
      legal_entity_reference: "legal-entity:test",
      profile: "treasury-settlement-ph-v1",
      profile_version: "v1",
      decision: "advisory_review_required",
      decision_references: [],
      invariant_references: [],
      trace_references: [],
    },
    commercial_terms_are_not_client_funds: true,
    commission_requires_attributed_participant: true,
    provider_cost_requires_authoritative_evidence: true,
  },
};

const partners = {
  schema: "x-change.cockpit.commercial-partners.v1",
  summary: {
    active_count: 1,
    awaiting_approval_count: 1,
    legacy_unresolved_count: 0,
    legacy_unresolved_minor: 0,
  },
  partners: [
    {
      id: 1,
      reference: "partner:acceptance",
      display_name: "Acceptance Partner",
      status: "active",
      revision: {
        id: 1,
        version: 1,
        legal_name: "Acceptance Partner Incorporated",
        attribution_basis: "contractual_referral",
        authorization_reference: "contract:acceptance",
        effective_at: "2026-08-08T00:00:00+00:00",
      },
      destinations: [
        {
          id: 1,
          version: 1,
          provider: "netbank",
          connection_reference: "netbank-primary",
          currency: "PHP",
          summary: "GCash · ••••4567",
          effective_at: "2026-08-08T00:00:00+00:00",
        },
      ],
      balances: [
        {
          currency: "PHP",
          earned_minor: 400,
          reserved_minor: 0,
          settled_minor: 0,
          available_minor: 400,
        },
      ],
    },
  ],
  pending_revisions: [
    {
      id: 2,
      partner_reference: "partner:pending",
      display_name: "Pending Partner",
      version: 1,
      attribution_basis: "marketing_agreement",
      authorization_reference: "contract:pending",
      submitted_at: "2026-08-08T00:00:00+00:00",
    },
  ],
  pending_destinations: [],
};

describe("Cockpit Commercial Offering administration", () => {
  it("keeps the Price List primary and exposes the Waterfall without mixing their meaning", async () => {
    const wrapper = mount(CommercialOfferings, {
      props: {
        commercialOffering: {
          profile: "pay_code",
          active,
          source: "installation_baseline",
          artifact,
          history,
          can_manage: true,
          can_approve: false,
          can_reconcile_provider_costs: true,
          can_request_commission_payouts: true,
          can_approve_commission_payouts: false,
          can_execute_commission_payouts: false,
          can_manage_partners: true,
          can_approve_partners: true,
          pending: [],
          published: [],
          governance: {
            state: "roles_ready",
            message:
              "Independent maker and checker authorities can govern revisions.",
            changes_locked: false,
            roles: {
              maker_count: 1,
              checker_count: 1,
              separation_ready: true,
            },
          },
          controls,
          partners,
        },
      },
      global: { stubs: { Head: true } },
    });

    expect(wrapper.text()).toContain("Price List & Waterfall");
    expect(wrapper.text()).toContain("Transaction Fee");
    expect(wrapper.text()).toContain("OTP Verification");
    expect(wrapper.text()).toContain("Submit New Version");

    const waterfallButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Waterfall"));

    expect(waterfallButton).toBeDefined();
    await waterfallButton!.trigger("click");

    expect(wrapper.text()).toContain("provider cost");
    expect(wrapper.text()).toContain("commercial revenue");
    expect(wrapper.text()).toContain("Independent Maker–Checker");

    const artifactButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Artifact"));

    expect(artifactButton).toBeDefined();
    await artifactButton!.trigger("click");
    expect(wrapper.text()).toContain("Commercial Offering YAML");
    expect(wrapper.text()).toContain("Frozen Source Artifact");
    expect(wrapper.text()).toContain("Version History");

    const partnersButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Partners"));

    expect(partnersButton).toBeDefined();
    await partnersButton!.trigger("click");

    expect(wrapper.text()).toContain("Commercial Partners");
    expect(wrapper.text()).toContain("Acceptance Partner");
    expect(wrapper.text()).toContain("GCash · ••••4567");
    expect(wrapper.text()).toContain("₱4.00 available");
    expect(wrapper.text()).toContain("Approval Inbox");
    expect(wrapper.text()).toContain("Pending Partner");

    const activityButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Activity"));

    expect(activityButton).toBeDefined();
    await activityButton!.trigger("click");

    expect(wrapper.text()).toContain("Commercial Positions");
    expect(wrapper.text()).toContain("Current system balances");
    expect(wrapper.text()).toContain("Lifetime Allocated");
    expect(wrapper.text()).toContain("Settled Or Paid");
    expect(wrapper.text()).toContain("Remaining");
    expect(wrapper.text()).toContain("Reconciled");
    expect(wrapper.text()).toContain("Allocation History");
    expect(wrapper.text()).toContain("Reversed sales are excluded");

    const operationsButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Operations"));

    expect(operationsButton).toBeDefined();
    await operationsButton!.trigger("click");

    expect(wrapper.text()).toContain("Provider Cost Payable");
    expect(wrapper.text()).toContain("Commission Available");
    expect(wrapper.text()).toContain("Provider Cost Evidence");
    expect(wrapper.text()).toContain("Record Authoritative Evidence");
    expect(wrapper.text()).toContain("Commission Payouts");
    expect(wrapper.text()).toContain("Request Commission Payout");
    expect(wrapper.text()).toContain("Acceptance Partner");
    expect(wrapper.text()).toContain("Awaiting Approval");
    expect(wrapper.text()).toContain("Review Required");
  });

  it("shows pending publication as a checker action without exposing edits", () => {
    const wrapper = mount(CommercialOfferings, {
      props: {
        commercialOffering: {
          profile: "pay_code",
          active,
          source: "installation_baseline",
          artifact,
          history,
          can_manage: false,
          can_approve: true,
          controls,
          partners,
          published: [],
          governance: {
            state: "revision_awaiting_approval",
            message: "A revision is waiting for approval.",
            changes_locked: false,
            roles: {
              maker_count: 1,
              checker_count: 1,
              separation_ready: true,
            },
          },
          pending: [
            {
              id: 7,
              reference: "commercial-offering:pay_code",
              version: 2,
              snapshot_hash: "a".repeat(64),
              effective_at: "2026-08-07T00:00:00+00:00",
              submitted_at: "2026-08-07T00:00:00+00:00",
              maker: { type: "App\\Models\\User", id: 5 },
            },
          ],
        },
      },
      global: { stubs: { Head: true } },
    });

    expect(wrapper.text()).toContain("Independent Approval");
    expect(wrapper.text()).toContain("Approve & Publish");
    expect(wrapper.text()).not.toContain("Submit New Version");
  });

  it("keeps published approval separate from activation", () => {
    const wrapper = mount(CommercialOfferings, {
      props: {
        commercialOffering: {
          profile: "pay_code",
          active,
          source: "installation_baseline",
          artifact,
          history,
          can_manage: false,
          can_approve: true,
          controls,
          partners,
          pending: [],
          published: [
            {
              id: 8,
              reference: "commercial-offering:pay_code",
              version: 2,
              snapshot_hash: "b".repeat(64),
              effective_at: "2026-08-07T00:00:00+00:00",
              approved_at: "2026-08-07T00:00:00+00:00",
            },
          ],
          governance: {
            state: "published_awaiting_activation",
            message: "An approved version is waiting for activation.",
            changes_locked: false,
            roles: {
              maker_count: 1,
              checker_count: 1,
              separation_ready: true,
            },
          },
        },
      },
      global: { stubs: { Head: true } },
    });

    expect(wrapper.text()).toContain("Ready For Activation");
    expect(wrapper.text()).toContain("Activate Version");
    expect(wrapper.text()).toContain("Publication records approval");
  });
});
