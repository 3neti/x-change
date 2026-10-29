import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import CockpitOnDemandIssuanceFundingDialog from "../../../resources/js/cockpit/components/CockpitOnDemandIssuanceFundingDialog.vue";

import { projection } from "./issuanceProjection";

describe("CockpitOnDemandIssuanceFundingDialog", () => {
  it("preserves QR Ph across refreshed projections and reopening, but resets for a new order", async () => {
    const initial = structuredClone(projection);
    initial.funding_selector.methods[1].available = true;
    initial.funding_selector.methods[1].selectable = true;
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: initial },
      global: { stubs: { Teleport: true } },
    });
    try {
      const qrTab = () => wrapper.get('[data-testid="funding-mode-self_top_up"]');
      await qrTab().trigger("click");
      expect(qrTab().attributes("aria-selected")).toBe("true");
      for (let refresh = 0; refresh < 3; refresh++) {
        await wrapper.setProps({ projection: structuredClone(initial) });
        expect(qrTab().attributes("aria-selected")).toBe("true");
      }
      await wrapper.setProps({ open: false });
      await wrapper.setProps({ open: true });
      expect(qrTab().attributes("aria-selected")).toBe("true");
      const nextOrder = structuredClone(initial);
      nextOrder.order.reference = "ORDER-2";
      await wrapper.setProps({ projection: nextOrder });
      expect(wrapper.get('[data-testid="funding-mode-bank_transfer"]').attributes("aria-selected")).toBe("true");
    } finally { wrapper.unmount(); }
  });

  it("keeps bank transfer primary and presents an exact read-only amount", () => {
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain(
      "Pay ₱50.00",
    );
    expect(document.body.querySelector("h2")?.textContent?.trim()).toBe("Pay ₱50.00");
    expect(document.body.textContent).toContain("113-001-00001-9");
    expect(document.body.textContent).toContain(
      "Auto-checking · Pay once",
    );
    expect(document.body.textContent).not.toContain("Choose Bank Transfer or QR Ph");
    expect(document.body.querySelector('details')?.hasAttribute('open')).toBe(false);
    expect(document.body.querySelector('[data-testid="funding-confirmation-notice"]')).toBeNull();
    expect(document.body.querySelector('[data-testid="funding-mode-pay_code"]')?.getAttribute('style')).toContain('display: none');
    expect(document.body.querySelector('[aria-label="Funding progress"]')).toBeNull();
    expect(
      document.body.querySelector(
        '[data-testid="public-issuance-recovery-link"]',
      ),
    ).not.toBeNull();
    expect(
      document.body.querySelector('[data-testid="on-demand-payment-check"]'),
    ).not.toBeNull();
    expect(document.body.querySelector('[aria-label="Close"]')).toBeNull();

    wrapper.unmount();
  });

  it("automatically requests an idempotent provider check while the order is open", async () => {
    vi.useFakeTimers();
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: async () => structuredClone(projection),
    } as Response);
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection },
      attachTo: document.body,
    });

    await vi.advanceTimersByTimeAsync(10_000);

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      projection.actions.show,
      expect.objectContaining({ credentials: "same-origin" }),
    );
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      projection.actions.verify,
      expect.objectContaining({ method: "POST" }),
    );

    wrapper.unmount();
    fetchMock.mockRestore();
    vi.useRealTimers();
  });

  it("carries the guest possession token on public funding-order polling", async () => {
    vi.useFakeTimers();
    const publicProjection = structuredClone(projection);
    publicProjection.guest_access_token = "guest-order-secret";
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: async () => structuredClone(publicProjection),
    } as Response);
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: publicProjection },
      attachTo: document.body,
    });

    await vi.advanceTimersByTimeAsync(10_000);

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      publicProjection.actions.show,
      expect.objectContaining({
        headers: expect.objectContaining({
          "X-XChange-Guest-Order-Token": "guest-order-secret",
        }),
      }),
    );
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      publicProjection.actions.verify,
      expect.objectContaining({
        headers: expect.objectContaining({
          "X-XChange-Guest-Order-Token": "guest-order-secret",
        }),
      }),
    );

    wrapper.unmount();
    fetchMock.mockRestore();
    vi.useRealTimers();
  });

  it("truthfully presents a verified payment that needs issuance attention", () => {
    const attentionProjection = structuredClone(projection);
    attentionProjection.status = "issuance_attention";
    attentionProjection.order.status = "issuance_attention";
    attentionProjection.order.can_cancel = false;
    attentionProjection.order.receipt.verified_at = "2026-09-29T09:01:00+08:00";
    attentionProjection.retry.eligible = true;
    attentionProjection.lifecycle.current = "attention";
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: attentionProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain(
      "Payment received — issuance needs attention",
    );
    expect(document.body.textContent).toContain(
      "Your payment was verified and remains protected",
    );
    expect(document.body.textContent).toContain("Do not pay again");
    expect(document.body.textContent).toContain("No Pay Code was issued");
    expect(document.body.textContent).not.toContain(
      "payment has not been accepted",
    );
    expect(document.body.textContent).not.toContain("I’ve made the transfer");
    expect(document.body.textContent).toContain("Refresh status");
    expect(document.body.textContent).toContain("Try issuing again");
    expect(document.body.textContent).not.toContain("Cancel safely");

    wrapper.unmount();
  });

  it("truthfully presents an attention state before payment is received", () => {
    const attentionProjection = structuredClone(projection);
    attentionProjection.status = "issuance_attention";
    attentionProjection.order.status = "issuance_attention";
    attentionProjection.order.can_cancel = true;
    attentionProjection.lifecycle.current = "attention";

    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: attentionProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain("Issuance needs attention");
    expect(document.body.textContent).toContain(
      "Payment instructions could not be prepared. No payment was requested.",
    );
    expect(document.body.textContent).not.toContain(
      "Payment received — issuance needs attention",
    );
    expect(document.body.textContent).not.toContain(
      "Your payment was verified and remains protected",
    );

    wrapper.unmount();
  });

  it("refreshes a paid order without repeating payment verification", async () => {
    const paidProjection = structuredClone(projection);
    paidProjection.status = "funded";
    paidProjection.order.status = "funded";
    paidProjection.order.can_cancel = false;
    paidProjection.monitor.eligible = false;
    paidProjection.lifecycle.current = "payment_verified";
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: async () => structuredClone(paidProjection),
    } as Response);
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: paidProjection },
      attachTo: document.body,
    });

    document.body
      .querySelector<HTMLButtonElement>(
        '[data-testid="on-demand-status-refresh"]',
      )
      ?.click();
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    expect(fetchMock).toHaveBeenCalledWith(
      paidProjection.actions.show,
      expect.objectContaining({ credentials: "same-origin" }),
    );

    wrapper.unmount();
    fetchMock.mockRestore();
  });

  it("requests a single guarded issuance retry for an eligible paid order", async () => {
    const attentionProjection = structuredClone(projection);
    attentionProjection.status = "issuance_attention";
    attentionProjection.order.status = "issuance_attention";
    attentionProjection.order.can_cancel = false;
    attentionProjection.retry.eligible = true;
    attentionProjection.monitor.eligible = false;
    const pendingProjection = structuredClone(attentionProjection);
    pendingProjection.retry.eligible = false;
    pendingProjection.retry.pending = true;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: async () => pendingProjection,
    } as Response);
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: attentionProjection },
      attachTo: document.body,
    });

    document.body
      .querySelector<HTMLButtonElement>(
        '[data-testid="on-demand-issuance-retry"]',
      )
      ?.click();
    await vi.waitFor(() =>
      expect(document.body.textContent).toContain("Retry requested"),
    );
    expect(fetchMock).toHaveBeenCalledWith(
      attentionProjection.actions.retry_issuance,
      expect.objectContaining({ method: "POST" }),
    );
    expect(
      document.body.querySelector('[data-testid="on-demand-issuance-retry"]'),
    ).toBeNull();

    wrapper.unmount();
    fetchMock.mockRestore();
  });

  it("keeps an active verification workspace open until it reaches an outcome", () => {
    const checkingProjection = structuredClone(projection);
    checkingProjection.status = "payer_acknowledged";
    checkingProjection.order.status = "payer_acknowledged";
    checkingProjection.lifecycle.current = "checking_payment";
    checkingProjection.lifecycle.message =
      "Payment is not visible yet. You do not need to pay again.";
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: checkingProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain("Payment is not visible yet");
    expect(document.body.textContent).toContain("Check now");
    expect(document.body.querySelector('[aria-label="Close"]')).toBeNull();
    expect(wrapper.emitted("cancelled")).toBeUndefined();

    wrapper.unmount();
  });

  it("states provider outages without implying nonpayment", () => {
    const unavailableProjection = structuredClone(projection);
    unavailableProjection.status = "payer_acknowledged";
    unavailableProjection.order.status = "payer_acknowledged";
    unavailableProjection.lifecycle.current = "checking_payment";
    unavailableProjection.lifecycle.verification_unavailable = true;
    unavailableProjection.lifecycle.message =
      "Verification is temporarily unavailable. You do not need to pay again.";
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: unavailableProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain(
      "Verification is temporarily unavailable",
    );
    expect(document.body.textContent).toContain("You do not need to pay again");

    wrapper.unmount();
  });

  it("turns mismatched payment evidence into a review-only workspace", () => {
    const reviewProjection = structuredClone(projection);
    reviewProjection.status = "underfunded";
    reviewProjection.order.status = "underfunded";
    reviewProjection.order.can_cancel = false;
    reviewProjection.monitor.eligible = false;
    reviewProjection.lifecycle.current = "underfunded";
    reviewProjection.lifecycle.message =
      "A payment was found, but it is below the exact amount. Do not pay again; this order needs review.";
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: reviewProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain("Payment needs review");
    expect(document.body.textContent).toContain("Do not pay again");
    expect(
      document.body.querySelector('[data-testid="on-demand-payment-review"]'),
    ).not.toBeNull();
    expect(document.body.textContent).not.toContain("Exact transfer amount");
    expect(document.body.textContent).not.toContain("Check now");
    expect(document.body.textContent).not.toContain("Cancel safely");
    expect(document.body.textContent).toContain("Close");

    wrapper.unmount();
  });

  it("renders an order-specific exact-amount QR Ph when the provider proves it", async () => {
    const fixedQrProjection = structuredClone(projection);
    fixedQrProjection.funding_selector.methods[1].available = true;
    fixedQrProjection.funding_selector.methods[1].selectable = true;
    fixedQrProjection.funding_selector.methods[1].unavailable_reason = null;
    fixedQrProjection.funding_selector.qr_ph = {
      fixed_amount: true,
      amount_minor: 5_000,
      currency: "PHP",
      image: "data:image/png;base64,ZmFrZQ==",
      qr_mode: "dynamic",
      transaction_type: "p2m",
      provider_generated: true,
      notice: "The exact amount is encoded in this order-specific QR.",
    };
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: fixedQrProjection },
      attachTo: document.body,
    });

    document
      .querySelector<HTMLButtonElement>(
        '[data-testid="funding-mode-self_top_up"]',
      )
      ?.click();
    await nextTick();

    expect(
      document.body.querySelector('[data-testid="on-demand-fixed-qr-ph"]'),
    ).not.toBeNull();
    expect(document.body.querySelector('[data-testid="on-demand-qr-ph-artifact"]')?.classList.contains('size-[232px]')).toBe(true);
    expect(document.body.textContent).not.toContain("The exact amount is encoded");
    expect(document.body.textContent).not.toContain("QR Ph · Scan to pay");
    expect(document.querySelector('[data-testid="on-demand-qr-ph-artifact"]')?.classList.contains('-translate-y-1/2')).toBe(true);
    expect(document.querySelector('[data-testid="funding-qr-amount"]')?.textContent).toBe('₱50.00');
    expect(document.body.textContent).toContain("Check now");
    expect(document.body.querySelector("img")?.getAttribute("src")).toBe(
      "data:image/png;base64,ZmFrZQ==",
    );

    wrapper.unmount();
  });

  it("replaces payment controls with a calm late-payment outcome after expiry", () => {
    const expiredProjection = structuredClone(projection);
    expiredProjection.status = "expired";
    expiredProjection.order.status = "expired";
    expiredProjection.order.can_cancel = false;
    expiredProjection.order.late_payment_disposition = "client_funds";
    expiredProjection.order.late_payment_detected_at =
      "2026-09-29T09:01:00+08:00";
    expiredProjection.monitor.eligible = false;
    expiredProjection.lifecycle.current = "expired";
    expiredProjection.lifecycle.message =
      "Your payment arrived after this order expired and was added to Client Funds. No Pay Code was issued.";
    const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
      props: { open: true, projection: expiredProjection },
      attachTo: document.body,
    });

    expect(document.body.textContent).toContain(
      "Payment added to Client Funds",
    );
    expect(document.body.textContent).toContain("Your money is safe");
    expect(document.body.textContent).not.toContain("Check now");
    expect(
      document.body.querySelector('[data-testid="on-demand-fixed-qr-ph"]'),
    ).toBeNull();
    expect(document.body.querySelector('[aria-label="Close"]')).not.toBeNull();

    wrapper.unmount();
  });
});

it("keeps payment and stamp on fixed-size faces without changing payment state", async () => {
  const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
    props: { open: true, projection, stampPreview: { amount: '50', currency: 'PHP', provider: 'netbank', recipient: 'Anyone', purpose: '', claimOutcome: 'provider_disbursement', voucherType: 'redeemable', expiry: '3 Days', instructionKeys: [], hasRiderDesign: false, riderDesignSource: 'default', riderDesignDocument: '', riderStamp: null, stampPresentation: null, claimEntryArtifact: null, quantity: '1' } },
    global: { stubs: { CockpitPayCodeCanvas: { template: '<div data-testid="stamp-fixture" />', mounted() { this.$emit('previewImage', 'data:image/png;base64,stamp'); } } } },
    attachTo: document.body,
  });
  const card = document.querySelector('[data-testid="funding-flip-card"]')!;
  const toggle = document.querySelector<HTMLButtonElement>('[data-testid="funding-flip-toggle"]')!;
  expect(card.classList.contains('h-[280px]')).toBe(true);
  toggle.click();
  await nextTick();
  expect(document.querySelector('[data-testid="funding-payment-face"]')?.getAttribute('inert')).not.toBeNull();
  expect(document.querySelector('[data-testid="funding-stamp-face"]')?.getAttribute('aria-hidden')).toBe('false');
  expect(toggle.textContent).toContain('Back to payment');
  expect(document.querySelector('[data-testid="funding-stamp-background"]')?.getAttribute('src')).toBe('data:image/png;base64,stamp');
  expect(wrapper.emitted('issued')).toBeUndefined();
  toggle.click();
  await nextTick();
  expect(document.querySelector('[data-testid="funding-payment-face"]')?.getAttribute('aria-hidden')).toBe('false');
  expect(card.classList.contains('h-[280px]')).toBe(true);
  wrapper.unmount();
});
