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
    expected_minor: 5000, settled_minor: 5000, currency: "PHP", refund_status: null, attention_reason: "issuance_attention_required",
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
});
