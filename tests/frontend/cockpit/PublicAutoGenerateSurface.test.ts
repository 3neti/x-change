import { config, mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CockpitQuickGenerateSubmitPanel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import PublicIssuanceLayout from "../../../resources/js/cockpit/layouts/PublicIssuanceLayout.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";

vi.mock("@inertiajs/vue3", () => ({
  Head: {
    template: "<div><slot /></div>",
  },
  Link: {
    props: ["href"],
    template: '<a :href="href?.url ?? href"><slot /></a>',
  },
  router: {
    patch: vi.fn(),
    post: vi.fn(),
    reload: vi.fn(),
  },
}));

config.global.stubs = {
  ...config.global.stubs,
  Teleport: true,
};

describe("Public Auto Generate surface", () => {
  it("starts blank while removing every template control", () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        publicMode: true,
        startupMode: "repeat_last",
        lastInstructions: {
          schema: "x-change.cockpit.quick-generate-last-instructions.v1",
          saved_at: "2026-10-01T00:00:00Z",
          instructions: {
            cash: { amount: 999, currency: "PHP" },
            metadata: {
              custom: {
                cockpit: { template_key: "money-changer" },
              },
            },
          },
        },
        showEngineeringPreview: false,
        showWorkspaceSwitcher: false,
        allowTemplateManagement: false,
        onDemandIssuancePolicy: {
          enabled: true,
          basis: "full_amount",
        },
      },
    });

    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-order-card"]')
        .exists(),
    ).toBe(true);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-surface-toggle"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-engineering-preview"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-starting-point"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-choose-template"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-save-template"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-repeat-last"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-submit-button"]')
        .exists(),
    ).toBe(true);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-issue-action-menu"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-voucher-type"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper.find('[data-testid="cockpit-value-use-control"]').exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-primary-settlement-rail"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper
        .find('[data-testid="public-auto-generate-after-claim-destination"]')
        .exists(),
    ).toBe(true);
    expect(
      wrapper
        .get('[data-testid="cockpit-quick-generate-primary-amount"]')
        .attributes("value"),
    ).toBe("");
  });

  it("presents the public tool as a claim-led product", () => {
    const wrapper = mount(PublicIssuanceLayout, {
      props: {
        navigation: {
          claim_url: "/x/claim",
          login_url: "/login",
          register_url: "/register",
        },
      },
      slots: {
        default: '<div data-testid="public-composer-slot">Composer</div>',
      },
    });

    expect(wrapper.text()).toContain("Create a Pay Code in seconds");
    expect(wrapper.text()).toContain("No asking for someone’s mobile number");
    expect(wrapper.text()).toContain("Let the meaning arrive with the money");
    expect(wrapper.text()).toContain("Pull, not push");
    expect(wrapper.text()).toContain("Send more than money");
    expect(wrapper.text()).toContain("Share it. They claim it. You know.");
    expect(wrapper.text()).toContain("Go professional");
    expect(wrapper.get('[data-testid="public-composer-slot"]').exists()).toBe(
      true,
    );
    expect(
      wrapper
        .get('[data-testid="public-auto-generate-sign-in"]')
        .attributes("href"),
    ).toBe("/login");
  });
});
