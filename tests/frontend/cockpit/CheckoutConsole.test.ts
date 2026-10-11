import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Console from "../../../resources/js/pages/x-change/checkout/Console.vue";

vi.mock("@inertiajs/vue3", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@inertiajs/vue3")>();
  return { ...actual, Head: { template: "<span />" } };
});

const monitor = {
  counts: { all: 1, awaiting_payment: 0, settled: 1, issued: 0, attention: 1, refund_open: 0 },
  filter: "all",
  rows: [{
    reference: "01CHECKOUT", order_reference: "01ORDER", status: "issuance_attention",
    method: "qr_ph", contact: "••••4567", contact_source: "provider_reported",
    expected_minor: 5000, settled_minor: 5000, currency: "PHP", pay_code: null, refund_status: null, refund_eligible: true, attention_reason: "issuance_attention_required",
    last_activity_at: "2026-10-11T00:00:00Z", timeline: [{ type: "payment_settled", at: "2026-10-11T00:00:00Z" }],
  }],
  pagination: { current_page: 1, last_page: 1, total: 1 },
};

describe("Checkout console", () => {
  it("does not show payment data to a locked visitor", () => {
    const wrapper = mount(Console, { props: { role: "locked", monitor: null }, global: { stubs: { Head: true } } });
    expect(wrapper.text()).toContain("Owner access");
    expect(wrapper.text()).not.toContain("01CHECKOUT");
  });

  it("keeps stakeholder monitoring read only", async () => {
    const wrapper = mount(Console, { props: { role: "viewer", monitor, viewer_token: "token" }, global: { stubs: { Head: true } } });
    expect(wrapper.text()).toContain("Read only");
    await wrapper.get("button").trigger("click");
    expect(wrapper.text()).toContain("payment settled");
    expect(wrapper.text()).not.toContain("Request guarded retry");
  });

  it("offers manual refund review for a paid expired order without offering issuance retry", async () => {
    const expiredMonitor = {
      ...monitor,
      rows: [{ ...monitor.rows[0], status: "expired", attention_reason: "payment_after_expired" }],
    };
    const wrapper = mount(Console, { props: { role: "owner", monitor: expiredMonitor }, global: { stubs: { Head: true } } });
    await wrapper.findAll('button').find((button) => button.text() === 'Inspect')?.trigger('click');
    expect(wrapper.text()).toContain("Paid without a Pay Code · refund review needed");
    expect(wrapper.text()).toContain("Open manual refund case");
    expect(wrapper.text()).not.toContain("Request guarded retry");
  });

  it("labels checkout and funding references and keeps payment separate from Pay Code value", async () => {
    const issuedMonitor = {
      ...monitor,
      rows: [{
        ...monitor.rows[0], status: "issued", expected_minor: 9000, settled_minor: 9000,
        pay_code: { masked_code: "••••Z123", value_minor: 7500, currency: "PHP", code: "READY-XYZ123" },
        refund_eligible: false,
      }],
    };
    const wrapper = mount(Console, { props: { role: "owner", monitor: issuedMonitor }, global: { stubs: { Head: true } } });
    expect(wrapper.text()).toContain("Checkout ID");
    expect(wrapper.text()).toContain("Funding order ID");
    expect(wrapper.text()).toContain("Payment due");
    expect(wrapper.text()).toContain("Payment received");
    expect(wrapper.text()).toContain("₱90.00");
    expect(wrapper.text()).toContain("₱75.00");
    expect(wrapper.text()).not.toContain("READY-XYZ123");

    await wrapper.findAll("button").find((button) => button.text() === "Inspect")?.trigger("click");
    expect(wrapper.text()).toContain("READY-XYZ123");

    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText } });
    await wrapper.findAll("button").find((button) => button.text() === "Copy Pay Code")?.trigger("click");
    expect(writeText).toHaveBeenCalledWith("READY-XYZ123");
    expect(wrapper.text()).toContain("Copied");

    const viewerMonitor = {
      ...issuedMonitor,
      rows: [{ ...issuedMonitor.rows[0], pay_code: { masked_code: "••••Z123", value_minor: 7500, currency: "PHP" } }],
    };
    const viewer = mount(Console, { props: { role: "viewer", monitor: viewerMonitor, viewer_token: "token" }, global: { stubs: { Head: true } } });
    await viewer.findAll("button").find((button) => button.text() === "Inspect")?.trigger("click");
    expect(viewer.text()).toContain("••••Z123");
    expect(viewer.text()).not.toContain("READY-XYZ123");
    expect(viewer.text()).not.toContain("Copy Pay Code");
  });
});
