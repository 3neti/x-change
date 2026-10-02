import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import PublicPricingPage from "../../../resources/js/pages/x-change/public/Pricing.vue";

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div><slot /></div>" },
  Link: {
    props: ["href"],
    template: '<a :href="href?.url ?? href"><slot /></a>',
  },
}));

const navigation = {
  pricing_url: "/x/pricing",
  claim_url: "/x/claim",
  create_url: "/x/auto-generate",
  login_url: "/login",
  register_url: "/register",
};

describe("Public pricing page", () => {
  it("renders authoritative groups, charging status, examples, and navigation", () => {
    const wrapper = mount(PublicPricingPage, {
      props: {
        public_navigation: navigation,
        pricing: {
          available: true,
          currency: "PHP",
          billing: {
            mode: "informational",
            customer_charging_active: false,
            heading: "Service prices are shown for transparency",
            message: "Listed prices are not currently added.",
          },
          provenance: {
            catalog_reference: "pay-code",
            catalog_version: 3,
            offering_reference: "pay-code-default",
            offering_version: 1,
          },
          groups: [
            {
              key: "base",
              label: "Base Pay Code",
              description: "Core issuance.",
              items: [
                {
                  code: "cash.amount",
                  name: "Transaction Fee",
                  amount_minor: 1500,
                },
              ],
            },
          ],
          examples: [
            {
              key: "send-50",
              label: "Send ₱50",
              description: "A straightforward Pay Code.",
              principal_minor: 5000,
              service_fees_minor: 1500,
              amount_due_minor: 5000,
            },
          ],
        },
      },
    });

    expect(wrapper.text()).toContain("What will my Pay Code cost?");
    expect(wrapper.text()).toContain("Transaction Fee");
    expect(wrapper.text()).toContain(
      "Service prices are shown for transparency",
    );
    expect(wrapper.text()).toContain(
      "Current active price list · Catalog v3 · Offering v1",
    );
    expect(wrapper.text()).toContain("Listed service fees");
    expect(wrapper.text()).toContain("Amount due now");
    expect(
      wrapper.get('[data-testid="public-pricing-create"]').attributes("href"),
    ).toBe("/x/auto-generate");
    expect(
      wrapper
        .get('[data-testid="public-navigation-pricing"]')
        .attributes("href"),
    ).toBe("/x/pricing");
  });

  it("renders a safe unavailable state without invented prices", () => {
    const wrapper = mount(PublicPricingPage, {
      props: {
        public_navigation: navigation,
        pricing: {
          available: false,
          currency: "PHP",
          groups: [],
          examples: [],
          provenance: null,
          billing: {
            mode: "informational",
            customer_charging_active: false,
            heading: "Service prices are shown for transparency",
            message: "Review the composer.",
          },
          unavailable_message: "Pricing is temporarily unavailable.",
        },
      },
    });

    expect(
      wrapper.get('[data-testid="public-pricing-unavailable"]').text(),
    ).toContain("Pricing temporarily unavailable");
    expect(wrapper.find('[data-testid="public-price-list"]').exists()).toBe(
      false,
    );
  });
});
