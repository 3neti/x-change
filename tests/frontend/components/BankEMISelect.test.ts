import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import BankEMISelect from "../../../resources/js/components/x-change-shared-financial/BankEMISelect.vue";

const institutions = [
  { key: "gcash", value: "GXCHPHM2XXX", name: "GCash", short_name: "GCash", category: "wallet", account_label: "GCash Mobile Number", identifier_scheme: "ph_mobile", aliases: ["G-Xchange"], commonly_used: true },
  { key: "pnb", value: "PNBMPHMMTOD", name: "Philippine National Bank", short_name: "PNB", category: "bank", account_label: "Account Number", identifier_scheme: "account_number", aliases: ["PNB"], commonly_used: true },
];

describe("Bank and wallet selector", () => {
  it("shows institution names without exposing routing codes", async () => {
    const wrapper = mount(BankEMISelect, { props: { institutions } });

    await wrapper.get('[data-testid="bank-emi-select-trigger"]').trigger("click");

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

    await wrapper.get('[data-testid="bank-emi-select-trigger"]').trigger("click");
    await wrapper.get('input[type="search"]').setValue("PNB");

    expect(wrapper.text()).toContain("Philippine National Bank");
    expect(wrapper.text()).not.toContain("Bank 0");
  });

  it("keeps Maya Wallet and Maya Bank as separate common choices", async () => {
    const wrapper = mount(BankEMISelect);

    await wrapper.get('[data-testid="bank-emi-select-trigger"]').trigger("click");

    expect(wrapper.text()).toContain("PayMaya Philippines Inc");
    expect(wrapper.text()).toContain("Maya Bank, Inc.");
  });

  it("shows a placeholder in the smart trigger before anything is selected", () => {
    const wrapper = mount(BankEMISelect, { props: { institutions } });

    const trigger = wrapper.get('[data-testid="bank-emi-select-trigger"]');

    expect(trigger.text()).toContain("Choose wallet or bank");
    expect(trigger.text()).not.toContain("GCash");
  });

  it("displays the selected destination's label (not icon-only) in the same trigger", () => {
    const wrapper = mount(BankEMISelect, {
      props: { institutions, modelValue: "GXCHPHM2XXX" },
    });

    const trigger = wrapper.get('[data-testid="bank-emi-select-trigger"]');

    expect(trigger.text()).toContain("GCash");
    expect(trigger.text()).not.toContain("Choose wallet or bank");
  });

  it("keeps Maya Wallet and Maya Bank textually distinct in the selected trigger", () => {
    const wallet = mount(BankEMISelect, {
      props: { institutions, modelValue: "PAPHPHM1XXX" },
    });
    const bank = mount(BankEMISelect, {
      props: { institutions, modelValue: "MYDBPHM2XXX" },
    });

    expect(wallet.get('[data-testid="bank-emi-select-trigger"]').text()).toContain("Maya Wallet");
    expect(bank.get('[data-testid="bank-emi-select-trigger"]').text()).toContain("Maya Bank");
  });

  it("renders cleanly with no broken image when the selected code has no packaged icon", () => {
    const wrapper = mount(BankEMISelect, {
      props: { institutions, modelValue: "NOT-A-REAL-CODE" },
    });

    const trigger = wrapper.get('[data-testid="bank-emi-select-trigger"]');

    expect(trigger.text()).toContain("NOT-A-REAL-CODE");
    expect(trigger.find("img").exists()).toBe(false);
  });

  it("propagates a search/select update from the underlying control to the selected destination", async () => {
    const wrapper = mount(BankEMISelect, { props: { institutions } });

    await wrapper.get('[data-testid="bank-emi-select-trigger"]').trigger("click");
    await wrapper.get('button[role="option"]').trigger("click");

    expect(wrapper.emitted("update:modelValue")).toEqual([["GXCHPHM2XXX"]]);

    await wrapper.setProps({ modelValue: "GXCHPHM2XXX" });

    expect(wrapper.get('[data-testid="bank-emi-select-trigger"]').text()).toContain("GCash");
  });
});
