import { flushPromises, mount } from "@vue/test-utils";
import { expect, it, vi } from "vitest";
import CockpitPaymentRecoveryReports from "../../resources/js/cockpit/components/CockpitPaymentRecoveryReports.vue";

it("loads unverified payer reports and records a review note", async () => {
  const request = vi
    .fn()
    .mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        reports: [
          {
            reference: "REPORT-1",
            status: "open",
            evidence: {
              transaction_reference: "TX-123",
              amount_minor: 5000,
              paid_at: "2026-11-03T12:00",
              contact: "payer@example.test",
            },
          },
        ],
      }),
    })
    .mockResolvedValueOnce({ ok: true });
  vi.stubGlobal("fetch", request);
  const wrapper = mount(CockpitPaymentRecoveryReports, {
    props: { code: "AB12" },
  });
  await wrapper.get("button").trigger("click");
  await flushPromises();
  expect(wrapper.text()).toContain("unverified");
  expect(wrapper.text()).toContain("TX-123");
  await wrapper.get("textarea").setValue("Checked provider evidence");
  await wrapper.findAll("button")[1].trigger("click");
  await flushPromises();
  expect(request).toHaveBeenLastCalledWith(
    "/x/cockpit/pay-codes/AB12/payment-recovery-reports/REPORT-1/review",
    expect.objectContaining({
      method: "POST",
      body: expect.stringContaining("Checked provider evidence"),
    }),
  );
  expect(wrapper.text()).toContain("reviewed");
  wrapper.unmount();
  vi.unstubAllGlobals();
});
