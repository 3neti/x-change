import { config, flushPromises, mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import CockpitQuickGenerateSubmitPanel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import BankEMISelect from "../../../resources/js/components/x-change-shared-financial/BankEMISelect.vue";
import PublicIssuanceLayout from "../../../resources/js/cockpit/layouts/PublicIssuanceLayout.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: {} }),
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
  it('opens template actions from the compact current-template control', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    expect(wrapper.get('[data-testid="cockpit-quick-generate-current-template"]').text()).toContain('Blank Pay Code');
    await wrapper.get('[data-testid="open-templates-editor"]').trigger('click');
    expect(wrapper.get('[data-testid="cockpit-quick-generate-template-toolbar"]').text()).toContain('Choose Template');
    await wrapper.get('[data-testid="cockpit-quick-generate-choose-template"]').trigger('click');
    expect(wrapper.find('[data-testid="cockpit-quick-generate-template-picker"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="cockpit-quick-generate-template-toolbar"]').exists()).toBe(false);
    wrapper.unmount();
  });
  it('places collection controls after Options', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates, invoiceDraftUrl: '/invoice-drafts' }, attachTo: document.body });
    await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
    const options = wrapper.get('[data-testid="cockpit-quick-generate-order-options-toggle"]').element;
    for (const selector of ['open-value-flow-editor', 'open-payer-documents-editor', 'fee-bearer-control', 'open-document-requests-editor']) {
      expect(options.compareDocumentPosition(wrapper.get(`[data-testid="${selector}"]`).element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    }
    wrapper.unmount();
  });
  it('edits document requests in a compact control', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
    expect(wrapper.get('[data-testid="document-requests-summary"]').text()).toBe('None requested');
    await wrapper.get('[data-testid="open-document-requests-editor"]').trigger('click');
    await wrapper.get('[data-testid="add-document-requirement"]').trigger('click');
    const fields = wrapper.get('[data-testid="document-requirement-control"]').findAll('select');
    await fields[1].setValue('before_payment');
    await fields[2].setValue('invited');
    await wrapper.get('[data-testid="document-requests-editor-done"]').trigger('click');
    expect(wrapper.get('[data-testid="document-requests-summary"]').text()).toBe('1 document requested');
    expect(JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text()).execution.document_requirements).toEqual([{ type: 'INVOICE', timing: 'before_payment', requires_acceptance: false, contributor: 'invited' }]);
    wrapper.unmount();
  });
  it('edits payer documents in a compact control', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ reference: '00000000-0000-4000-8000-000000000001', document_id: '01ARZ3NDEKTSV4RRFFQ69G5FAV' }) }));
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates, invoiceDraftUrl: '/invoice-drafts' }, attachTo: document.body });
    try {
      await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
      expect(wrapper.get('[data-testid="payer-documents-summary"]').text()).toBe('Optional · None added');
      await wrapper.get('[data-testid="open-payer-documents-editor"]').trigger('click');
      await wrapper.get('[data-testid="issuer-payment-details"] textarea').setValue('Laptop repair');
      await wrapper.get('[data-testid="issuer-payment-details"] input').setValue('INV-1');
      const input = wrapper.get('[data-testid="issuer-invoice-upload"] input');
      Object.defineProperty(input.element, 'files', { configurable: true, value: [new File(['invoice'], 'invoice.pdf', { type: 'application/pdf' })] });
      await input.trigger('change');
      await flushPromises();
      expect(wrapper.get('[data-testid="issuer-invoice-upload"]').text()).toContain('invoice.pdf');
      await wrapper.get('[data-testid="payer-documents-editor-done"]').trigger('click');
      expect(wrapper.get('[data-testid="payer-documents-summary"]').text()).toBe('1 document added');
      expect(JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text()).payment_details.documents[0].label).toBe('invoice.pdf');
      expect(JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text()).payment_details).toMatchObject({ description: 'Laptop repair', reference: 'INV-1' });
    } finally {
      wrapper.unmount();
      vi.unstubAllGlobals();
    }
  });
  it('shows the current estimated service fee for either payer', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { total: 10, currency: 'PHP' } }) }));
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    try {
      await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
      await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('100');
      await vi.advanceTimersByTimeAsync(600);
      await flushPromises();
      expect(wrapper.get('[data-testid="fee-bearer-summary"]').text()).toBe('Issuer pays ₱10.00 at issuance');
      await wrapper.get('[data-testid="fee-bearer-control"]').trigger('click');
      await wrapper.get('[data-testid="fee-bearer-payer"]').setValue();
      await vi.advanceTimersByTimeAsync(600);
      await flushPromises();
      expect(wrapper.get('[data-testid="fee-bearer-summary"]').text()).toBe('Customer pays ₱10.00 with the payment');
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { total: 0, currency: 'PHP' } }) }));
      await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('101');
      await vi.advanceTimersByTimeAsync(600);
      await flushPromises();
      expect(wrapper.get('[data-testid="fee-bearer-summary"]').text()).toBe('Customer pays ₱0.00 with the payment');
    } finally {
      wrapper.unmount();
      vi.unstubAllGlobals();
      vi.useRealTimers();
    }
  });
  it('edits and summarizes who pays the service fee', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
    expect(wrapper.get('[data-testid="fee-bearer-summary"]').text()).toBe('Issuer pays at issuance');
    expect(wrapper.get('[data-testid="fee-bearer-estimate-status"]').text()).toBe('Complete the order details to calculate the fee');
    await wrapper.get('[data-testid="fee-bearer-control"]').trigger('click');
    await wrapper.get('[data-testid="fee-bearer-payer"]').setValue();
    await wrapper.get('[data-testid="fee-bearer-editor-done"]').trigger('click');
    expect(wrapper.get('[data-testid="fee-bearer-summary"]').text()).toBe('Customer pays with the payment');
    expect(JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text()).fee_bearer).toBe('payer');
    wrapper.unmount();
  });
  it('summarizes and edits the collection destination in a dialog', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    await wrapper.get('input[name="cockpit-quick-generate-voucher-type"][value="payable"]').setValue();
    expect(wrapper.get('[data-testid="value-flow-summary"]').text()).toBe('Client Funds');
    await wrapper.get('[data-testid="open-value-flow-editor"]').trigger('click');
    expect(wrapper.get('[data-testid="value-flow-destination"]').exists()).toBe(true);
    const done = wrapper.get<HTMLButtonElement>('[data-testid="value-flow-editor-done"]');
    expect(done.element.disabled).toBe(false);
    await wrapper.get('[data-testid="value-flow-destination"] select').setValue(true);
    expect(done.element.disabled).toBe(true);
    wrapper.getComponent(BankEMISelect).vm.$emit('update:modelValue', 'GXCHPHM2XXX');
    await wrapper.get('[data-testid="value-flow-destination"] input[inputmode="numeric"]').setValue('09');
    expect(done.element.disabled).toBe(true);
    await wrapper.get('[data-testid="value-flow-destination"] input[inputmode="numeric"]').setValue('09179999999');
    expect(done.element.disabled).toBe(false);
    expect(wrapper.get('[data-testid="value-flow-summary"]').text()).toContain('09179999999');
    await done.trigger('click');
    await wrapper.get('[data-testid="cockpit-pay-code-canvas-flow-button"]').trigger('click');
    expect(wrapper.get('[data-testid="cockpit-pay-code-canvas-flow"]').text()).toContain('09179999999');
    wrapper.unmount();
  });
  it('shows the live flow in the Pay Code card', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    expect(wrapper.find('[data-testid="cockpit-pay-code-canvas-flow"]').exists()).toBe(false);
    await wrapper.get('[data-testid="cockpit-pay-code-canvas-flow-button"]').trigger('click');
    const panel = wrapper.get('[data-testid="cockpit-pay-code-canvas-flow"]');
    expect(panel.text()).toContain('What will happen');
    expect(wrapper.get('[data-testid="cockpit-pay-code-canvas-flow-button"]').attributes('aria-selected')).toBe('true');
    wrapper.unmount();
  });
  it('opens and closes the Options dialog with the existing controls', async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates }, attachTo: document.body });
    const toggle = wrapper.get('[data-testid="cockpit-quick-generate-order-options-toggle"]');
    expect(toggle.attributes('aria-haspopup')).toBe('dialog');
    expect(wrapper.find('[data-testid="cockpit-quick-generate-order-options-panel"]').exists()).toBe(false);
    await toggle.trigger('click');
    const panel = wrapper.get('[data-testid="cockpit-quick-generate-order-options-panel"]');
    expect(panel.find('[data-testid="cockpit-claim-requirements-control"]').exists()).toBe(true);
    expect(panel.find('[data-testid="cockpit-quick-generate-primary-feedback"]').exists()).toBe(true);
    expect(panel.find('[data-testid="cockpit-value-use-control"]').exists()).toBe(true);
    expect(panel.find('[data-testid="cockpit-quick-generate-primary-settlement-rail"]').exists()).toBe(true);
    const done = wrapper.findAll('button').find(button => button.text() === 'Done' && button.element.closest('[role="dialog"]'));
    expect(done).toBeDefined();
    await done!.trigger('click');
    expect(wrapper.find('[data-testid="cockpit-quick-generate-order-options-panel"]').exists()).toBe(false);
    wrapper.unmount();
  });
  it.each([true, false])('keeps purpose and rider instruction synchronized through the rider editor in public mode %s', async (publicMode) => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates, publicMode }, attachTo: document.body });
    expect(wrapper.get('[data-testid="open-rider-editor"]').attributes('aria-label')).toBe('Edit rider');
    expect(wrapper.get('[data-testid="open-rider-editor"]').element.parentElement).toBe(wrapper.get('[data-testid="cockpit-quick-generate-primary-purpose"]').element.parentElement);
    await wrapper.get('[data-testid="open-rider-editor"]').trigger('click');
    await wrapper.get('[data-testid="cockpit-quick-generate-submit-purpose"]').setValue('Thank you for your purchase');
    await wrapper.get('[data-testid="rider-editor"]').findAll('[role="tab"]').find(tab => tab.text().includes('Link'))!.trigger('mousedown', { button: 0 });
    expect(wrapper.text()).toContain('Add a URL to see its preview.');
    await wrapper.get('[data-testid="rider-editor-url"]').setValue('https://example.test/thanks');
    expect(wrapper.get('[data-testid="rider-editor-url-preview"]').attributes('sandbox')).toBe('');
    await wrapper.get('[data-testid="rider-editor"]').findAll('[role="tab"]').find(tab => tab.text().includes('Splash'))!.trigger('mousedown', { button: 0 });
    expect(wrapper.text()).toContain('Add splash content to see its preview.');
    await wrapper.get('[data-testid="rider-editor-splash"]').setValue('Welcome');
    expect(wrapper.get('[data-testid="rider-editor-splash-preview"]').attributes('srcdoc')).toContain('Welcome');
    expect((wrapper.get('[data-testid="cockpit-quick-generate-primary-purpose"]').element as HTMLInputElement).value).toBe('Thank you for your purchase');
    const instruction = JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
    expect(instruction.rider).toMatchObject({ message: 'Thank you for your purchase', url: 'https://example.test/thanks', splash: 'Welcome' });
    wrapper.unmount();
  });
  it("keeps the after-claim destination control on Auto Generate", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: { templates: cockpitQuickGenerateTemplates, publicMode: true },
    });
    await wrapper.get('[data-testid="cockpit-quick-generate-order-options-toggle"]').trigger('click');
    const input = wrapper.get('[data-testid="public-auto-generate-rider-url"]');
    await input.setValue('https://example.test/thank-you');
    const instruction = JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
    expect(instruction.rider.url).toBe('https://example.test/thank-you');
  });
  it("does not show the separate destination control on Quick Generate", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: { templates: cockpitQuickGenerateTemplates, publicMode: false },
    });
    await wrapper.get('[data-testid="cockpit-quick-generate-order-options-toggle"]').trigger('click');
    expect(wrapper.find('[data-testid="public-auto-generate-after-claim-destination"]').exists()).toBe(false);
  });
  it("starts blank while removing every template control", async () => {
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
    await wrapper.get('[data-testid="cockpit-quick-generate-order-options-toggle"]').trigger('click');
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

  it("presents the public tool as a claim-led product", async () => {
    const wrapper = mount(PublicIssuanceLayout, {
      props: {
        navigation: {
          pricing_url: "/x/pricing",
          claim_url: "/x/claim",
          create_url: "/x/auto-generate",
          login_url: "/login",
          register_url: "/register",
        },
        batch_recovery_endpoints: {
          request: "/discover",
          verify: "/verify",
          select: "/select",
        },
      },
      slots: {
        default: '<div data-testid="public-composer-slot">Composer</div>',
      },
    });

    expect(wrapper.text()).toContain("Create a Pay Code in seconds");
    expect(
      wrapper
        .get('[data-testid="public-navigation"] [data-testid="public-batch-recovery-launcher"]')
        .exists(),
    ).toBe(true);
    expect(wrapper.text()).toContain("Live public utility");
    expect(wrapper.text()).toContain("No asking for someone’s mobile number");
    expect(wrapper.text()).toContain(
      "This uses real money. Nothing is issued until payment is confirmed.",
    );
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
        .get('[data-testid="public-navigation-sign-in"]')
        .attributes("href"),
    ).toBe("/login");
    expect(
      wrapper
        .get('[data-testid="public-navigation-brand-mark"]')
        .attributes("src"),
    ).toBe("/vendor/x-change/images/pay-code/pay-code-mark.svg");
    expect(
      wrapper
        .get('[data-testid="public-navigation-pricing"]')
        .attributes("href"),
    ).toBe("/x/pricing");
    expect(
      wrapper
        .get('[data-testid="public-auto-generate-pricing-link"]')
        .attributes("href"),
    ).toBe("/x/pricing");
    const primaryAction = wrapper.get(
      '[data-testid="public-auto-generate-primary-action"]',
    );

    expect(primaryAction.element.tagName).toBe("BUTTON");
    expect(primaryAction.attributes("type")).toBe("button");

    await primaryAction.trigger("click");

    expect(wrapper.emitted("create")).toHaveLength(1);
    expect(wrapper.emitted("create")?.[0]?.[0]).toBe(primaryAction.element);
  });

  it("offers commissioned Reusable Balance without a top-up control", async () => {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
      props: {
        templates: cockpitQuickGenerateTemplates,
        publicMode: true,
        instructionCapabilities: {
          stored_value: {
            key: "stored_value",
            label: "Reusable Balance",
            status: "ready",
            issuance_allowed: true,
            claim_retryable: false,
            missing_configuration: [],
            source: "wallet-stored-value",
          },
        },
      },
    });

    await wrapper
      .get('[data-testid="cockpit-quick-generate-order-options-toggle"]')
      .trigger("click");
    const reusableBalance = wrapper.get<HTMLInputElement>(
      '[data-testid="cockpit-value-use-reusable-balance"]',
    );

    expect(reusableBalance.element.disabled).toBe(false);
    await reusableBalance.setValue(true);
    expect(wrapper.find('[data-testid="value-flow-summary"]').exists()).toBe(true);
    await wrapper.get('[data-testid="cockpit-value-use-trigger"]').trigger("click");

    expect(
      wrapper.get('[data-testid="cockpit-value-use-stored-value-settings"]').text(),
    ).toContain("Starting balance");
    expect(wrapper.find('[data-testid="cockpit-value-use-replenishable"]').exists()).toBe(false);
  });
});
