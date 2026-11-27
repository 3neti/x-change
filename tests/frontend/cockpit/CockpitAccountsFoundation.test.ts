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
  it("offers the fresh-start control only when the local commissioning capability is supplied", async () => {
    const base = {
      account_overview: accountOverview, cockpit_header_read_model: headerReadModel,
      can_manage_bank_accounts: true, can_configure_bank_accounts: true, personal_netbank_funding_enabled: true,
      bank_accounts: [{ id: 1, provider: "netbank", nickname: "Test", account_name: "Example Corp",
        account_number: "991100001234", is_default: false, funding_ready: false, disbursement_ready: false }],
    };
    const wrapper = mount(Accounts, { props: { ...base, personal_netbank_fresh_commissioning_available: true } });
    await wrapper.findAll("button").find((button) => button.text() === "Set up funding")!.trigger("click");
    expect(wrapper.text()).toContain("Local test: new funding requests only");
    expect(wrapper.text()).toContain("Previous");
    expect(wrapper.text()).toContain("only system generating");
    const restricted = mount(Accounts, { props: { ...base, personal_netbank_fresh_commissioning_available: false } });
    await restricted.findAll("button").find((button) => button.text() === "Set up funding")!.trigger("click");
    expect(restricted.text()).not.toContain("Local test: new funding requests only");
  });

  it("prioritizes funds, issuance, and masked funding destinations", () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        bank_accounts: [],
        can_manage_bank_accounts: false, can_configure_bank_accounts: false,
        cockpit_header_read_model: headerReadModel,
      },
    });

    expect(wrapper.text()).toContain("Your Account");
    expect(wrapper.text()).toContain("No personal bank accounts connected.");
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
        bank_accounts: [],
        can_manage_bank_accounts: false, can_configure_bank_accounts: false,
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

  it("shows assigned accounts and their separate readiness", () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        can_manage_bank_accounts: false, can_configure_bank_accounts: false,
        cockpit_header_read_model: headerReadModel,
        bank_accounts: [{
          id: 1, provider: "netbank", nickname: "Payroll", account_name: "Example Corp",
          account_number: "991100001234", is_default: true,
          funding_ready: true, disbursement_ready: false,
        }],
      },
    });

    expect(wrapper.text()).toContain("Payroll");
    expect(wrapper.text()).toContain("991100001234");
    expect(wrapper.text()).toContain("Funding: Ready");
    expect(wrapper.text()).toContain("Issuance: Not ready");
    expect(wrapper.text()).toContain("Ask your Treasury administrator to finish setting up issuance for this account.");
    expect(wrapper.text()).not.toContain("Ask your Treasury administrator to finish setting up funding for this account.");
  });

  it("lets an administrator set up issuance for a connected account", async () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        cockpit_header_read_model: headerReadModel,
        treasury_connections: ["personal"],
        can_manage_bank_accounts: true, can_configure_bank_accounts: true,
        bank_accounts: [{ id: 1, provider: "netbank", nickname: "Payroll", account_name: "Example Corp",
          account_number: "991100001234", is_default: false, funding_ready: false, disbursement_ready: false }],
      },
    });
    await wrapper.findAll("button").find((button) => button.text() === "Set up issuance")!.trigger("click");
    expect(wrapper.text()).toContain("Treasury connection");
    expect(wrapper.text()).toContain("permitted to send payouts");
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
  });

  it("offers funding setup only when enabled for an administrator", async () => {
    const props = {
      account_overview: accountOverview,
      cockpit_header_read_model: headerReadModel,
      can_manage_bank_accounts: true, can_configure_bank_accounts: true,
      personal_netbank_funding_enabled: true,
      bank_accounts: [{ id: 1, provider: "netbank", nickname: "Payroll", account_name: "Example Corp",
        account_number: "991100001234", is_default: false, funding_ready: false, disbursement_ready: true }],
    };
    const wrapper = mount(Accounts, { props });
    await wrapper.findAll("button").find((button) => button.text() === "Set up funding")!.trigger("click");
    expect(wrapper.text()).toContain("reviewed and recorded previously used VCA references");
    await wrapper.setProps({ personal_netbank_funding_enabled: false });
    expect(wrapper.findAll("button").some((button) => button.text() === "Set up funding")).toBe(false);
  });

  it("lets an administrator configure another user's account without changing their default", () => {
    const wrapper = mount(Accounts, { props: {
      account_overview: accountOverview, cockpit_header_read_model: headerReadModel,
      can_manage_bank_accounts: true, can_configure_bank_accounts: true, personal_netbank_funding_enabled: true,
      bank_accounts: [{ id: 1, owner_id: "42", is_owner: false, provider: "netbank", nickname: null,
        account_name: "Example Corp", account_number: "991100001234", is_default: false,
        funding_ready: false, disbursement_ready: true }],
    }});
    expect(wrapper.text()).toContain("User 42");
    expect(wrapper.text()).toContain("Set up funding");
    expect(wrapper.text()).not.toContain("Set as default");
    expect(wrapper.text()).not.toContain("Edit nickname");
  });

  it("shows the add-account form only to an authorized administrator", async () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        cockpit_header_read_model: headerReadModel,
        bank_accounts: [],
        can_manage_bank_accounts: true, can_configure_bank_accounts: true,
      },
    });

    const button = wrapper.findAll("button").find((item) => item.text() === "Add account");
    expect(button).toBeDefined();
    await button!.trigger("click");
    expect(wrapper.text()).toContain("Registered account name");
    expect(wrapper.text()).toContain("VCA alias");
  });

  it("keeps readiness controls hidden from an account manager without configuration authority", () => {
    const wrapper = mount(Accounts, {
      props: {
        account_overview: accountOverview,
        cockpit_header_read_model: headerReadModel,
        can_manage_bank_accounts: true,
        can_configure_bank_accounts: false,
        bank_accounts: [{ id: 1, provider: "netbank", nickname: "Payroll", account_name: "Example Corp",
          account_number: "991100001234", is_default: false, funding_ready: false, disbursement_ready: false }],
      },
    });

    expect(wrapper.text()).toContain("Add account");
    expect(wrapper.text()).not.toContain("Set up issuance");
    expect(wrapper.text()).not.toContain("Set up funding");
  });
});
