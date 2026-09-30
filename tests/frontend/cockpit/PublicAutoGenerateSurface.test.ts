import { config, mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CockpitQuickGenerateSubmitPanel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";

vi.mock("@inertiajs/vue3", () => ({
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
        .get('[data-testid="cockpit-quick-generate-primary-amount"]')
        .attributes("value"),
    ).toBe("");
  });
});
