import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import TreasuryOperations from "../../../resources/js/cockpit/pages/TreasuryOperations.vue";

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div />" },
  router: { post: vi.fn() },
}));

describe("Cockpit Treasury Operations", () => {
  it("shows a compact maker-checker Account Grant workspace", () => {
    const wrapper = mount(TreasuryOperations, {
      props: {
        treasuryAccountGrantStoreUrl:
          "/x/cockpit/treasury-operations/account-grants",
        treasuryInstitutionFundStoreUrl:
          "/x/cockpit/treasury-operations/institution-funds",
        treasuryReconciliationStoreUrl:
          "/x/cockpit/treasury-operations/reconciliation-runs",
        treasuryReconciliation: {
          can_view: true,
          can_request: true,
          can_approve: true,
          can_execute: true,
          connections: [
            {
              reference: "netbank-primary",
              provider: "netbank",
              currency: "PHP",
            },
          ],
          runs: [
            {
              reference: "RECON-001",
              status: "approved",
              connection_reference: "netbank-primary",
              provider: "netbank",
              currency: "PHP",
              purpose: "Verify owner funding",
              maker: "Amelia",
              checker: "Michael",
              provider_balance: null,
              internal_balance: null,
              difference: null,
              evidence_reference: null,
              reason: null,
              observed_at: null,
              actions: {
                approve: "/reconciliation/approve",
                execute: "/reconciliation/execute",
              },
            },
          ],
        },
        treasuryInstitutionFunds: {
          can_view: true,
          can_request: true,
          can_approve: true,
          can_execute: true,
          balance: "₱250.00",
          candidates: [
            {
              operation_reference: "opening-position-recognition:001",
              evidence_reference: "provider-evidence:001",
              amount_minor: 25000,
              amount: "₱250.00",
              currency: "PHP",
              connection_reference: "netbank-primary",
              available: true,
              observed_at: "2026-08-15T00:00:00Z",
            },
          ],
          classifications: [
            {
              reference: "CLASS-001",
              status: "awaiting_approval",
              amount: "₱250.00",
              ownership_basis: "Shareholder deposit",
              evidence_reference: "provider-evidence:001",
              maker: "Amelia",
              checker: null,
              updated_at: "2026-08-15T00:00:00Z",
              actions: {
                approve: "/classification/approve",
                execute: "/classification/execute",
              },
            },
          ],
        },
        treasuryAccountGrants: {
          can_view: true,
          can_request: true,
          can_approve: true,
          can_execute: true,
          test_allocations_available: true,
          connections: [
            {
              reference: "netbank-primary",
              provider: "netbank",
              currency: "PHP",
            },
          ],
          recipients: [{ id: "5", name: "Amelia", identity: "•••• 3656" }],
          grants: [
            {
              reference: "GRANT-001",
              status: "awaiting_approval",
              recipient: { name: "Amelia", identity: "•••• 3656" },
              amount: "₱1,000.00",
              purpose: "Beta testing allocation",
              test_allocation: true,
              maker: "Lester",
              checker: null,
              actions: { approve: "/approve", execute: "/execute" },
            },
          ],
        },
      },
      global: {
        stubs: {
          CockpitLayout: { template: "<main><slot /></main>" },
        },
      },
    });

    expect(wrapper.text()).toContain("Treasury Operations");
    expect(wrapper.text()).toContain("Institution-Owned Funds");
    expect(wrapper.text()).toContain("Provider Reconciliation");
    expect(wrapper.text()).toContain("Submit Check For Approval");
    expect(wrapper.text()).toContain("Check Provider");
    expect(wrapper.text()).toContain("Authoritative Deposit Evidence");
    expect(wrapper.text()).toContain("Submit Classification");
    expect(wrapper.text()).toContain("Shareholder deposit");
    expect(wrapper.text()).toContain("Submit For Approval");
    expect(wrapper.text()).toContain("Test Allocation");
    expect(wrapper.text()).toContain("Maker · Lester");
    expect(wrapper.text()).toContain("Approve");
    expect(wrapper.html()).toContain("min-w-0");
  });
});
