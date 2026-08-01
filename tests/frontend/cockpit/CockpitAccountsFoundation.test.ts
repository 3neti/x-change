import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import Accounts from "../../../resources/js/cockpit/pages/Accounts.vue";

const accountOverview = {
  schema: "x-change.cockpit.depositor-account.v1" as const,
  status: "available" as const,
  account: {
    reference: "Account •••• 12345678",
    currency: "PHP",
  },
  funding_destinations: [
    {
      code: "netbank",
      label: "NetBank",
      mode: "shared" as const,
      status: "ready",
      display_reference: "•••• 0019 · VCA 91500",
    },
    {
      code: "paynamics_constellation",
      label: "Paynamics",
      mode: "shared" as const,
      status: "not_configured",
      display_reference: null,
    },
  ],
};

const headerReadModel = {
  balances: [
    {
      key: "internal",
      label: "Client Funds",
      value: "₱1,250.00",
      tone: "healthy" as const,
    },
    {
      key: "outstanding",
      label: "Outstanding Pay Codes",
      value: "₱250.00",
      tone: "warning" as const,
    },
    {
      key: "issuance",
      label: "Issuance Capacity",
      value: "₱1,000.00",
      tone: "healthy" as const,
    },
  ],
};

describe("Cockpit depositor Account", () => {
  it("prioritizes funds, issuance, and masked funding destinations", () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        cockpit_header_read_model: headerReadModel,
      },
    });

    expect(wrapper.text()).toContain("Your Account");
    expect(wrapper.text()).toContain("₱1,250.00");
    expect(wrapper.text()).toContain("₱250.00");
    expect(wrapper.text()).toContain("₱1,000.00");
    expect(wrapper.text().match(/Client Funds/g)).toHaveLength(1);
    expect(wrapper.text().match(/Outstanding Pay Codes/g)).toHaveLength(1);
    expect(wrapper.text().match(/Issuance Capacity/g)).toHaveLength(1);
    expect(wrapper.text()).toContain("Account •••• 12345678");
    expect(wrapper.text()).toContain("•••• 0019 · VCA 91500");
    expect(wrapper.text()).toContain("Not connected");
    expect(
      wrapper.get('[data-testid="account-add-funds"]').attributes("href"),
    ).toBe("/x/cockpit/funding");
    expect(
      wrapper.get('[data-testid="account-create-pay-code"]').attributes("href"),
    ).toBe("/x/cockpit/quick-generate");
  });

  it("does not render provider administration or Treasury internals", () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        cockpit_header_read_model: headerReadModel,
      },
    });

    expect(wrapper.text()).not.toContain("Corporate account number");
    expect(wrapper.text()).not.toContain("VCA alias");
    expect(wrapper.text()).not.toContain("Lifecycle walkthrough");
    expect(wrapper.text()).not.toContain("Connection history");
    expect(wrapper.text()).not.toContain("Funding QR merchant profile");
    expect(wrapper.text()).not.toContain("registration token");
  });
});
