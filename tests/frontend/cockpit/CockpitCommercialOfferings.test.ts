import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CommercialOfferings from "../../../resources/js/cockpit/pages/CommercialOfferings.vue";

vi.mock("@inertiajs/vue3", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@inertiajs/vue3")>()),
  Head: { template: "<div><slot /></div>" },
}));

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
  provider_costs: {
    settled_count: 1,
    settled_minor: 1000,
    variance_minor: 0,
  },
  commissions: {
    earned_minor: 100,
    requested_minor: 0,
    settled_minor: 0,
    open_count: 0,
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

describe("Cockpit Commercial Offering administration", () => {
  it("keeps the Price List primary and exposes the Waterfall without mixing their meaning", async () => {
    const wrapper = mount(CommercialOfferings, {
      props: {
        commercialOffering: {
          profile: "pay_code",
          active,
          source: "installation_baseline",
          can_manage: true,
          can_approve: false,
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
        },
      },
      global: { stubs: { Head: true } },
    });

    expect(wrapper.text()).toContain("Price List & Waterfall");
    expect(wrapper.text()).toContain("Transaction Fee");
    expect(wrapper.text()).toContain("OTP Verification");
    expect(wrapper.text()).toContain("Submit New Version");

    await wrapper.get("button:nth-of-type(2)").trigger("click");

    expect(wrapper.text()).toContain("provider cost");
    expect(wrapper.text()).toContain("commercial revenue");
    expect(wrapper.text()).toContain("Independent Maker–Checker");
  });

  it("shows pending publication as a checker action without exposing edits", () => {
    const wrapper = mount(CommercialOfferings, {
      props: {
        commercialOffering: {
          profile: "pay_code",
          active,
          source: "installation_baseline",
          can_manage: false,
          can_approve: true,
          controls,
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
          can_manage: false,
          can_approve: true,
          controls,
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
