import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, expect, it, vi } from "vitest";
import CockpitValueFlowRecovery from "../../resources/js/cockpit/components/CockpitValueFlowRecovery.vue";

afterEach(() => vi.unstubAllGlobals());

it("shows payment and delivery separately and requires confirmation before retry", async () => {
  const fetchMock = vi
    .fn()
    .mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        payments: [
          {
            number: 1,
            amount_minor: 2000,
            currency: "PHP",
            received_at: null,
            delivery_status: "review",
            destination: "*******9820",
            retry_url: "/retry/1",
          },
        ],
      }),
    })
    .mockResolvedValueOnce({ ok: true })
    .mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        payments: [
          {
            number: 1,
            amount_minor: 2000,
            currency: "PHP",
            received_at: null,
            delivery_status: "pending",
            destination: "*******9820",
            retry_url: null,
          },
        ],
      }),
    });
  vi.stubGlobal("fetch", fetchMock);
  const wrapper = mount(CockpitValueFlowRecovery, { props: { code: "PMKA" } });
  await wrapper.get("button").trigger("click");
  await flushPromises();
  expect(wrapper.text()).toContain("Payment received");
  expect(wrapper.text()).toContain("Transfer needs review");
  expect(wrapper.findAll("button")[1].attributes("disabled")).toBeDefined();
  await wrapper.get('input[type="checkbox"]').setValue(true);
  await wrapper.findAll("button")[1].trigger("click");
  await flushPromises();
  expect(fetchMock.mock.calls[1][0]).toBe("/retry/1");
  expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({
    destination_ready: true,
  });
  expect(wrapper.text()).toContain("Transfer processing");
});

it("requests a fresh provider status check without implying that it has finished", async () => {
  const fetchMock = vi
    .fn()
    .mockResolvedValueOnce({
      ok: true,
      json: async () => ({ payments: [], check_url: "/check" }),
    })
    .mockResolvedValueOnce({ ok: true });
  vi.stubGlobal("fetch", fetchMock);
  const wrapper = mount(CockpitValueFlowRecovery, { props: { code: "PMKA" } });
  await wrapper.get("button").trigger("click");
  await flushPromises();
  await wrapper.findAll("button")[1].trigger("click");
  await flushPromises();
  expect(fetchMock.mock.calls[1][0]).toBe("/check");
  expect(wrapper.text()).toContain("Status check requested");
});
