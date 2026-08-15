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
        treasuryAccountGrantStoreUrl: "/x/cockpit/treasury-operations/account-grants",
        treasuryAccountGrants: {
          can_request: true,
          can_approve: true,
          can_execute: true,
          test_allocations_available: true,
          connections: [
            { reference: "netbank-primary", provider: "netbank", currency: "PHP" },
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
    expect(wrapper.text()).toContain("Submit For Approval");
    expect(wrapper.text()).toContain("Test Allocation");
    expect(wrapper.text()).toContain("Maker · Lester");
    expect(wrapper.text()).toContain("Approve");
    expect(wrapper.html()).toContain("min-w-0");
  });
});
