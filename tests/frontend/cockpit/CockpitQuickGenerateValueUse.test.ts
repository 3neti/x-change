import { config, flushPromises, mount } from "@vue/test-utils";
import { afterEach, vi, describe, expect, it } from "vitest";
import CockpitQuickGenerateSubmitPanel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";

vi.mock("@inertiajs/vue3", () => ({
  Link: {
    props: ["href"],
    template: '<a :href="href?.url ?? href"><slot /></a>',
  },
  router: {
    reload: vi.fn(),
  },
}));

config.global.stubs = {
  ...config.global.stubs,
  Teleport: true,
};

afterEach(() => {
  vi.unstubAllGlobals();
});

function preview(wrapper: ReturnType<typeof mount>): Record<string, any> {
  return JSON.parse(
    wrapper
      .get('[data-testid="cockpit-quick-generate-engineering-preview-json"]')
      .text(),
  );
}

function storedValueCapability() {
  return {
    key: "stored_value",
    label: "Reusable Balance",
    status: "ready" as const,
    issuance_allowed: true,
    claim_retryable: false,
    missing_configuration: [],
    source: "wallet-stored-value",
  };
}

describe("Cockpit Quick Generate value use", () => {
  it("submits the executable equal plan selected from the Order card", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        status: "issued",
        result: { code: "EQ-PLAN" },
      }),
    });

    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("crypto", {
      randomUUID: () => "equal-plan-idempotency",
    });

    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        mutationContract: {
          runtime_enabled: true,
          route: "x-change.cockpit.quick-generate.store",
          route_url: "/x/cockpit/quick-generate",
          allowed_methods: ["POST"],
        },
      },
    });

    await wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .setValue("100");
    await wrapper
      .get('[data-testid="cockpit-value-use-trigger"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-mode-fixed"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-fixed-count"]')
      .setValue("4");
    await wrapper
      .get('[data-testid="cockpit-value-use-done"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();

    const [, options] = fetchMock.mock.calls[0];
    const payload = JSON.parse(options.body);

    expect(payload.slice_plan).toMatchObject({
      schema: "voucher.slice-plan.v1",
      mode: "equal",
      selection: "next_only",
      total_minor: 10000,
    });
    expect(payload.slice_plan.slices).toHaveLength(4);
    expect(payload.slice_plan.slices.map((slice: any) => slice.label)).toEqual([
      "Slice 1",
      "Slice 2",
      "Slice 3",
      "Slice 4",
    ]);
    expect(payload.metadata.custom.cockpit.slice_plan.mode).toBe("fixed");
  });

  it("submits the executable flexible plan selected from Claim Experience", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        status: "issued",
        result: { code: "FLEX-PLAN" },
      }),
    });

    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("crypto", {
      randomUUID: () => "flexible-plan-idempotency",
    });

    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        mutationContract: {
          runtime_enabled: true,
          route: "x-change.cockpit.quick-generate.store",
          route_url: "/x/cockpit/quick-generate",
          allowed_methods: ["POST"],
        },
      },
    });

    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-amount"]')
      .setValue("100");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-slice-mode-open"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-max-slices"]')
      .setValue("3");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-min-withdrawal"]')
      .setValue("30");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();

    const [, options] = fetchMock.mock.calls[0];
    const payload = JSON.parse(options.body);

    expect(payload.slice_plan).toEqual({
      schema: "voucher.slice-plan.v1",
      mode: "flexible",
      selection: "flexible_amount",
      total_minor: 10000,
      currency: "PHP",
      slices: [],
      max_slices: 3,
      min_amount_minor: 3000,
    });
    expect(payload.metadata.custom.cockpit.slice_plan.mode).toBe("open");
  });

  it("commits a calculator amount while Reusable Balance is enabled", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        instructionCapabilities: {
          stored_value: storedValueCapability(),
        },
      },
    });

    await wrapper
      .get('[data-testid="cockpit-value-use-reusable-balance"]')
      .setValue(true);
    await wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="numeric-keypad-quick-100"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="numeric-keypad-confirm"]')
      .trigger("click");
    await flushPromises();

    expect(
      wrapper.get<HTMLInputElement>(
        '[data-testid="cockpit-quick-generate-primary-amount"]',
      ).element.value,
    ).toBe("100.00");
    expect(preview(wrapper).cash.amount).toBe(100);
    expect(preview(wrapper).stored_value).toMatchObject({
      enabled: true,
      maximum_balance: 100,
    });
  });

  it("preserves Flexible claim settings when amount changes", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
      },
    });

    await wrapper
      .get('[data-testid="cockpit-value-use-trigger"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-mode-open"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-max-claims"]')
      .setValue("7");
    await wrapper
      .get('[data-testid="cockpit-value-use-minimum-claim"]')
      .setValue("30");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .setValue("250");
    await flushPromises();

    expect(preview(wrapper).slice_plan).toMatchObject({
      mode: "flexible",
      max_slices: 7,
      min_amount_minor: 3000,
      total_minor: 25000,
    });
  });

  it("compiles a typed Reusable Balance draft and restores the previous slice plan", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        instructionCapabilities: {
          stored_value: storedValueCapability(),
        },
      },
    });

    await wrapper
      .get('[data-testid="cockpit-value-use-trigger"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-mode-open"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-max-claims"]')
      .setValue("6");
    await wrapper
      .get('[data-testid="cockpit-value-use-minimum-claim"]')
      .setValue("30");
    await wrapper
      .get('[data-testid="cockpit-value-use-done"]')
      .trigger("click");

    await wrapper
      .get('[data-testid="cockpit-value-use-reusable-balance"]')
      .setValue(true);
    await flushPromises();

    const storedValuePreview = preview(wrapper);

    expect(storedValuePreview.stored_value).toEqual({
      enabled: true,
      replenishable: false,
      maximum_balance: storedValuePreview.cash.amount,
      otp_required_above: null,
    });
    expect(storedValuePreview.cash).not.toHaveProperty("slice_mode");
    expect(storedValuePreview.cash).not.toHaveProperty("settlement_rail");
    expect(storedValuePreview.metadata.custom.cockpit).not.toHaveProperty(
      "slice_plan",
    );
    expect(
      wrapper
        .find('[data-testid="cockpit-quick-generate-primary-settlement-rail"]')
        .exists(),
    ).toBe(false);
    expect(
      wrapper.get('[data-testid="cockpit-quick-generate-voucher-kind"]').text(),
    ).toBe("Stored Value");

    await wrapper
      .get('[data-testid="cockpit-value-use-reusable-balance"]')
      .setValue(false);
    await flushPromises();

    expect(preview(wrapper).slice_plan).toMatchObject({
      mode: "flexible",
      max_slices: 6,
      min_amount_minor: 3000,
    });
  });

  it("renders Reusable Balance disabled when no durable capability is commissioned", () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
      },
    });

    expect(
      wrapper.get<HTMLInputElement>(
        '[data-testid="cockpit-value-use-reusable-balance"]',
      ).element.disabled,
    ).toBe(true);
    expect(
      wrapper
        .get('[data-testid="cockpit-value-use-stored-value-unavailable"]')
        .text(),
    ).toContain("durable wallet engine");
  });
});
