import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import CockpitFundingQrAddress from "../../../resources/js/cockpit/components/CockpitFundingQrAddress.vue";

const address = {
  reference: "01J-STANDING-1",
  provider: "netbank" as const,
  funding_address: "9150012345678901",
  masked_funding_address: "•••• 678901",
  purpose: "account_funding" as const,
  recognition_mode: "automatic" as const,
  status: "active" as const,
  currency: "PHP",
  institution: "NetBank",
  merchant_name: "TREASURY DESK - MANILA",
  qr_code: "data:image/png;base64,REUSABLE",
  qr_mode: "static" as const,
  transaction_type: "p2m" as const,
  embedded_amount: false as const,
  provider_generated: true as const,
  temporary: false as const,
  funding_intent_created: false as const,
  automatic_credit_enabled: true,
  minimum_amount_minor: 100,
  maximum_amount_minor: 5_000_000,
  daily_limit_minor: 10_000_000,
};

const profile = {
  name: "Treasury Desk",
  city: "Manila",
  merchant_category_code: "0000",
  merchant_name_template: "{name} - {city}",
  rendered_label: "TREASURY DESK - MANILA",
  maximum_label_length: 25,
  uppercase: true,
  application_name: "X-Change",
  template_options: [
    { value: "{name}" as const, label: "Name" },
    {
      value: "{name} - {city}" as const,
      label: "Name + City",
    },
    {
      value: "{app_name} - {name}" as const,
      label: "x-change + Name",
    },
  ],
  category_options: [],
  presentation_only: true as const,
  controls_routing: false as const,
  controls_settlement: false as const,
};

describe("Cockpit funding QR address", () => {
  it("centers a prominent QR beneath its authoritative payer label", () => {
    const wrapper = mount(CockpitFundingQrAddress, {
      props: { address, profile },
    });

    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-preview"]').text(),
    ).toBe("TREASURY DESK - MANILA");
    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-state"]').text(),
    ).toBe("Applied to this QR");
    expect(
      wrapper.get('[data-testid="standing-funding-address-qr"]').classes(),
    ).toEqual(expect.arrayContaining(["size-64", "sm:size-72"]));
  });

  it("previews format changes and blocks labels over 25 characters", async () => {
    const wrapper = mount(CockpitFundingQrAddress, {
      props: { address, profile },
    });
    const textInputs = wrapper.findAll('input[type="text"]');

    await textInputs[0].setValue("Amy");
    await textInputs[1].setValue("QC");

    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-preview"]').text(),
    ).toBe("AMY - QC");
    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-state"]').text(),
    ).toBe("Preview · Update QR to apply");
    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-count"]').text(),
    ).toContain("8 / 25");

    await textInputs[0].setValue("1234567890123456789012345");
    await textInputs[1].setValue("Manila");

    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-preview"]').text(),
    ).toBe("1234567890123456789012345");
    expect(
      wrapper.get('[data-testid="funding-qr-merchant-label-state"]').text(),
    ).toBe("9 characters too long");
    expect(
      wrapper.get('[data-testid="funding-qr-update"]').attributes("disabled"),
    ).toBeDefined();
  });

  it("offers every approved compact payer-label format", () => {
    const wrapper = mount(CockpitFundingQrAddress, {
      props: { address, profile },
    });

    expect(
      wrapper
        .findAll('input[name="merchant_name_template"]')
        .map((input) => input.attributes("value")),
    ).toEqual(["{name}", "{name} - {city}", "{app_name} - {name}"]);
  });
});
