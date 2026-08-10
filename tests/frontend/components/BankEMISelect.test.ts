import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import BankEMISelect from "../../../resources/js/components/x-change-shared-financial/BankEMISelect.vue";

const institutions = [
  { key: "gcash", value: "GXCHPHM2XXX", name: "GCash", short_name: "GCash", category: "wallet", account_label: "GCash Mobile Number", identifier_scheme: "ph_mobile", aliases: ["G-Xchange"], commonly_used: true },
  { key: "pnb", value: "PNBMPHMMTOD", name: "Philippine National Bank", short_name: "PNB", category: "bank", account_label: "Account Number", identifier_scheme: "account_number", aliases: ["PNB"], commonly_used: true },
];

describe("Bank and wallet selector", () => {
  it("shows institution names without exposing routing codes", () => {
    const wrapper = mount(BankEMISelect, { props: { institutions } });

    expect(wrapper.text()).toContain("GCash");
    expect(wrapper.text()).toContain("Philippine National Bank");
    expect(wrapper.text()).not.toContain("GXCHPHM2XXX");
    expect(wrapper.text()).not.toContain("PNBMPHMMTOD");
  });

  it("searches the canonical names and aliases", async () => {
    const expanded = [
      ...institutions,
      ...Array.from({ length: 7 }, (_, index) => ({ ...institutions[1], key: `bank-${index}`, value: `BANK-${index}`, name: `Bank ${index}`, short_name: `B${index}`, aliases: [], commonly_used: false })),
    ];
    const wrapper = mount(BankEMISelect, { props: { institutions: expanded } });

    await wrapper.get('input[type="search"]').setValue("PNB");

    expect(wrapper.text()).toContain("Philippine National Bank");
    expect(wrapper.text()).not.toContain("Bank 0");
  });

  it("keeps Maya Wallet and Maya Bank as separate common choices", () => {
    const wrapper = mount(BankEMISelect);

    expect(wrapper.text()).toContain("PayMaya Philippines Inc");
    expect(wrapper.text()).toContain("Maya Bank, Inc.");
  });
});
