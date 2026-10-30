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
  usePage: () => ({ props: {} }),
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

async function openOrderOptions(
  wrapper: ReturnType<typeof mount>,
): Promise<void> {
  await wrapper
    .get('[data-testid="cockpit-quick-generate-order-options-toggle"]')
    .trigger("click");
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
    await openOrderOptions(wrapper);
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

    const [, options] = fetchMock.mock.calls.find(
      ([url, request]) =>
        url === "/x/cockpit/quick-generate" && request.method === "POST",
    )!;
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

  it("edits and submits Scheduled portions without leaving the Order modal", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        status: "issued",
        result: { code: "SCHEDULED-PLAN" },
      }),
    });

    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("crypto", {
      randomUUID: () => "scheduled-plan-idempotency",
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
      .setValue("120");
    await openOrderOptions(wrapper);
    await wrapper
      .get('[data-testid="cockpit-value-use-trigger"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-value-use-mode-named"]')
      .trigger("click");
    await flushPromises();

    expect(
      wrapper.get('[data-testid="cockpit-value-use-dialog"]').isVisible(),
    ).toBe(true);
    expect(
      wrapper.findAll('[data-testid^="cockpit-scheduled-portion-"]'),
    ).not.toHaveLength(0);
    expect(
      wrapper.get<HTMLInputElement>(
        '[data-testid="cockpit-scheduled-portion-0-amount"]',
      ).element.value,
    ).toBe("60");
    expect(
      wrapper.get<HTMLInputElement>(
        '[data-testid="cockpit-scheduled-portion-1-amount"]',
      ).element.value,
    ).toBe("60");

    await wrapper
      .get('[data-testid="cockpit-scheduled-portion-0-description"]')
      .setValue("Morning fare");
    await wrapper
      .get('[data-testid="cockpit-scheduled-portions-add"]')
      .trigger("click");
    await flushPromises();

    expect(
      wrapper.get('[data-testid="cockpit-value-use-trigger"]').text(),
    ).toContain("Scheduled · 3 portions");
    expect(
      wrapper.get<HTMLInputElement>(
        '[data-testid="cockpit-scheduled-portion-0-description"]',
      ).element.value,
    ).toBe("Morning fare");
    expect(preview(wrapper).slice_plan).toMatchObject({
      schema: "voucher.slice-plan.v1",
      mode: "scheduled",
      selection: "one_or_many",
      total_minor: 12000,
    });
    expect(preview(wrapper).slice_plan.slices).toHaveLength(3);

    await wrapper
      .get('[data-testid="cockpit-scheduled-portion-0-claim-on"]')
      .setValue("2026-09-02");
    await wrapper
      .get('[data-testid="cockpit-scheduled-portion-0-claim-by"]')
      .setValue("2026-09-01");
    await flushPromises();

    expect(
      wrapper.get('[data-testid="cockpit-scheduled-portions-error"]').text(),
    ).toContain("cannot expire before");
    expect(
      wrapper.get<HTMLButtonElement>('[data-testid="cockpit-value-use-done"]')
        .element.disabled,
    ).toBe(true);

    await wrapper
      .get('[data-testid="cockpit-scheduled-portion-0-claim-by"]')
      .setValue("2026-09-03");
    await wrapper
      .get('[data-testid="cockpit-value-use-done"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();

    const [, options] = fetchMock.mock.calls.find(
      ([url, request]) =>
        url === "/x/cockpit/quick-generate" && request.method === "POST",
    )!;
    const payload = JSON.parse(options.body);

    expect(payload.slice_plan).toMatchObject({
      mode: "scheduled",
      selection: "one_or_many",
    });
    expect(payload.slice_plan.slices[0]).toMatchObject({
      id: "slice_1",
      label: "Morning fare",
      claim_on: "2026-09-02",
      claim_by: "2026-09-03",
    });
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

    await openOrderOptions(wrapper);
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

    await openOrderOptions(wrapper);
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

    await openOrderOptions(wrapper);
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
    expect(storedValuePreview.inputs.fields).toContain("mobile", "otp");
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
      (
        wrapper.get('[data-testid="cockpit-value-use-reusable-balance"]')
          .element as HTMLInputElement
      ).checked,
    ).toBe(true);

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

it('submits a voucher-owned destination and omits it when returning to ordinary disbursement', async () => {
  const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ status: 'issued', result: { code: 'FIXED' } }) });
  vi.stubGlobal('fetch', fetchMock);
  vi.stubGlobal('crypto', { randomUUID: () => 'fixed-destination' });
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
    props: {
      templates: cockpitQuickGenerateTemplates,
      instructionCapabilities: { stored_value: storedValueCapability() },
      mutationContract: { runtime_enabled: true, route: 'x-change.cockpit.quick-generate.store', route_url: '/x/cockpit/quick-generate', allowed_methods: ['POST'] },
    },
  });
  await openOrderOptions(wrapper);
  expect(wrapper.find('[data-testid="value-flow-destination"]').exists()).toBe(false);
  await wrapper.get('[data-testid="cockpit-value-use-reusable-balance"]').setValue(true);
  const destination = wrapper.findComponent({ name: 'ValueFlowDestination' });
  expect(destination.exists()).toBe(true);
  destination.vm.$emit('update:modelValue', 'instapay.gcash.mobile:09179999999');
  await flushPromises();
  expect(preview(wrapper).execution.value_flow.destination).toBe('instapay.gcash.mobile:09179999999');
  await wrapper.get('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
  await flushPromises();
  const request = fetchMock.mock.calls.find(([url, options]) => url === '/x/cockpit/quick-generate' && options.method === 'POST');
  expect(request).toBeDefined();
  expect(JSON.parse(request![1].body).execution.value_flow).toEqual({ destination: 'instapay.gcash.mobile:09179999999' });
  await wrapper.get('[data-testid="cockpit-value-use-reusable-balance"]').setValue(false);
  expect(wrapper.find('[data-testid="value-flow-destination"]').exists()).toBe(false);
  expect(preview(wrapper).execution).not.toHaveProperty('value_flow');
});


it('shows the destination for payable and settlement vouchers', async () => {
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates } });
  await openOrderOptions(wrapper);
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue(true);
  expect(wrapper.find('[data-testid="value-flow-destination"]').exists()).toBe(true);
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"] input').setValue(true);
  expect(wrapper.find('[data-testid="value-flow-destination"]').exists()).toBe(true);
});

it('places a payable destination under v2 execution in the preview and submitted instructions', async () => {
  const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ status: 'issued', result: { code: 'FIXED' } }) });
  vi.stubGlobal('fetch', fetchMock);
  vi.stubGlobal('crypto', { randomUUID: () => 'v2-destination' });
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: {
    templates: cockpitQuickGenerateTemplates,
    mutationContract: { runtime_enabled: true, route: 'x-change.cockpit.quick-generate.store', route_url: '/x/cockpit/quick-generate', allowed_methods: ['POST'] },
  } });
  await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('100');
  await openOrderOptions(wrapper);
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue(true);
  wrapper.findComponent({ name: 'ValueFlowDestination' }).vm.$emit('update:modelValue', 'instapay.gcash.mobile:09179999999');
  await flushPromises();
  expect(preview(wrapper).execution).toEqual({ schema: 'voucher.execution.v2', driver: 'default', value_flow: { destination: 'instapay.gcash.mobile:09179999999' } });
  expect(preview(wrapper)).not.toHaveProperty('value_flow');
  await wrapper.get('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
  await flushPromises();
  const request = fetchMock.mock.calls.find(([url, options]) => url === '/x/cockpit/quick-generate' && options.method === 'POST');
  expect(request).toBeDefined();
  expect(JSON.parse(request![1].body).execution).toEqual(preview(wrapper).execution);
});

it('builds the zero-disbursement PhilHealth attestation, review and payment workflow', async () => {
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates } });
  await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('20000');
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"] input').setValue(true);
  await wrapper.get('[data-testid="philhealth-settlement-workflow"]').setValue(true);
  await openOrderOptions(wrapper);
  wrapper.findComponent({ name: 'ValueFlowDestination' }).vm.$emit('update:modelValue', 'instapay.gcash.mobile:09179999999');
  await flushPromises();
  const instruction = preview(wrapper);
  expect(instruction.cash.amount).toBe(0);
  expect(instruction.target_amount).toBe(20000);
  expect(instruction.metadata.flow_type).toBe('settlement');
  expect(instruction.inputs.fields).toContain('signature');
  expect(instruction.execution).toMatchObject({
    schema: 'voucher.execution.v2', driver: 'settlement_envelope',
    settlement: { driver: 'philhealth-bst', readiness_gate: 'settleable' },
    value_flow: { destination: 'instapay.gcash.mobile:09179999999' },
    events: { attest: { actor: 'beneficiary' }, review: { actor: 'hospital_reviewer' }, settle: { actor: 'payer' } },
  });
});

it('builds payable subscriptions from the supplied event catalog', async () => {
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: {
    templates: cockpitQuickGenerateTemplates,
    v2EventCatalog: {
      'settlement.payment_collected': { label: 'Payment confirmed', stage: 'settle', workflows: ['payable', 'philhealth'] },
      'settlement.payout_failed': { label: 'Payout failed', stage: 'settle', workflows: ['payable'], requires_destination: true },
    },
  } });
  await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('100');
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue(true);
  await openOrderOptions(wrapper);
  await wrapper.get('[data-testid="v2-event-add-webhook"]').trigger('click');
  expect(wrapper.find('[data-testid="v2-event-0-settlement.payout_failed"]').exists()).toBe(false);
  await wrapper.get('[data-testid="v2-event-webhook-url-0"]').setValue('https://example.test/events');
  await wrapper.get('[data-testid="v2-event-0-settlement.payment_collected"]').setValue(true);
  await wrapper.get('[data-testid="v2-event-add-webhook"]').trigger('click');
  await wrapper.get('[data-testid="v2-event-webhook-url-1"]').setValue('https://example.test/other');
  await wrapper.get('[data-testid="v2-event-1-settlement.payment_collected"]').setValue(true);
  const instruction = preview(wrapper);
  expect(instruction.feedback.webhooks).toEqual([
    { url: 'https://example.test/events', events: ['settlement.payment_collected'] },
    { url: 'https://example.test/other', events: ['settlement.payment_collected'] },
  ]);
  expect(instruction.execution.events.settle).toMatchObject({ actor: 'payer', requires: ['confirmed_payment'] });
  wrapper.findComponent({ name: 'ValueFlowDestination' }).vm.$emit('update:modelValue', 'instapay.gcash.mobile:09179999999');
  await flushPromises();
  await wrapper.get('[data-testid="v2-event-0-settlement.payout_failed"]').setValue(true);
  expect(preview(wrapper).feedback.webhooks[0].events).toEqual(['settlement.payment_collected', 'settlement.payout_failed']);
  wrapper.findComponent({ name: 'ValueFlowDestination' }).vm.$emit('update:modelValue', '');
  await flushPromises();
  expect(wrapper.text()).toContain('Remove events that are unavailable for this workflow.');
});


it('includes the selected fee bearer on payable and settlement instructions', async () => {
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates } });
  await openOrderOptions(wrapper);
  expect(wrapper.find('[data-testid="fee-bearer-control"]').exists()).toBe(false);
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue(true);
  expect(preview(wrapper).fee_bearer).toBe('issuer');
  await wrapper.get('[data-testid="fee-bearer-payer"]').setValue(true);
  expect(preview(wrapper).fee_bearer).toBe('payer');
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"] input').setValue(true);
  expect(preview(wrapper).fee_bearer).toBe('payer');
});


it('builds an explicit settlement with separate principal, target and fixed repayment', async () => {
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates } });
  await openOrderOptions(wrapper);
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"] input').setValue(true);
  await wrapper.get('[data-testid="cockpit-quick-generate-target-amount"]').setValue('120');
  await wrapper.get('[data-testid="settlement-repayment-enabled"]').setValue(true);
  await wrapper.get('[data-testid="settlement-repayment-amount"]').setValue('30');
  const instruction = preview(wrapper);
  expect(instruction.target_amount).toBe(120);
  expect(instruction.rules).toMatchObject({ settlement_mode: 'disburse_then_repay', min_payment: 30, max_payment: 30 });
  expect(instruction.fee_bearer).toBe('issuer');
  await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue(true);
  expect(preview(wrapper).rules).not.toHaveProperty('settlement_mode');
});
