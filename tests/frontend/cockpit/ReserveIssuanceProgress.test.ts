import { describe, expect, it, vi, afterEach } from "vitest";
import { defineComponent } from "vue";
import { flushPromises, mount } from "@vue/test-utils";
import { useReserveIssuanceProgress } from "../../../resources/js/cockpit/composables/useReserveIssuanceProgress";

const pending = { reference: "rsv-1", status: "processing", amount_minor: 1000, currency: "PHP", pay_code: null, status_url: "/status/1", detail_url: null };

afterEach(() => { vi.unstubAllGlobals(); vi.useRealTimers(); });

function createProgress() {
  let progress!: ReturnType<typeof useReserveIssuanceProgress>;
  const wrapper = mount(defineComponent({ setup() { progress = useReserveIssuanceProgress("/reserve"); return () => null; } }));
  return { progress, wrapper };
}

describe("reserve issuance progress", () => {
  it("restores an existing pending issuance and blocks another request", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ issuances: [pending] }) }));
    const { progress, wrapper } = createProgress();
    await progress.restore();
    expect(progress.issuance.value?.reference).toBe("rsv-1");
    expect(progress.blocking.value).toBe(true);
    wrapper.unmount();
  });

  it("recovers a completed issuance after the response is lost", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ issuances: [{ ...pending, status: "issued", pay_code: "AA317" }] }) }));
    const { progress, wrapper } = createProgress();
    await progress.restore();
    expect(progress.issuance.value?.pay_code).toBe("AA317");
    expect(progress.blocking.value).toBe(false);
    wrapper.unmount();
  });

  it("fails closed on a malformed recovery response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) }));
    const { progress, wrapper } = createProgress();
    await progress.restore();
    expect(progress.error.value).toBe(true);
    expect(progress.blocking.value).toBe(true);
    wrapper.unmount();
  });

  it("fails closed if recovery cannot be checked", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("offline")));
    const { progress, wrapper } = createProgress();
    await progress.restore();
    expect(progress.error.value).toBe(true);
    expect(progress.blocking.value).toBe(true);
    wrapper.unmount();
  });

  it("checks a pending request and shows the issued code without submitting again", async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ ...pending, status: "issued", pay_code: "AA317", detail_url: "/detail" }) });
    vi.stubGlobal("fetch", fetchMock);
    const { progress, wrapper } = createProgress();
    progress.checking.value = false;
    progress.track(pending as Parameters<typeof progress.track>[0]);
    await vi.advanceTimersByTimeAsync(5000);
    await flushPromises();
    expect(fetchMock).toHaveBeenCalledOnce();
    expect(progress.issuance.value?.pay_code).toBe("AA317");
    expect(progress.blocking.value).toBe(false);
    wrapper.unmount();
  });
});
