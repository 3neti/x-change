import { mount } from "@vue/test-utils";
import { defineComponent, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  useIssuanceProgress,
  type IssuanceProgress,
} from "../../../resources/js/cockpit/composables/useIssuanceProgress";
import { projection } from "./issuanceProjection";
import type { CockpitOnDemandIssuanceFundingProjection as Projection } from "../../../resources/js/cockpit/types";

function order(status: string): Projection {
  const value = structuredClone(projection);
  value.status = value.order.status = status;
  value.monitor.eligible = status === "awaiting_payment";
  return value;
}
function response(value: Projection, status = 200, headers = {}): Response {
  return {
    ok: status === 200,
    status,
    headers: new Headers(headers),
    json: async () => value,
  } as Response;
}
const wrappers: ReturnType<typeof mount>[] = [];
function mountProgress(initial: Projection | null, storageKey?: string) {
  let progress!: IssuanceProgress;
  const onIssued = vi.fn();
  const wrapper = mount(
    defineComponent({
      setup() {
        progress = useIssuanceProgress(ref(initial), { onIssued, storageKey });
        return () => null;
      },
    }),
  );
  wrappers.push(wrapper);
  return { progress, onIssued, wrapper };
}
beforeEach(() => {
  vi.useFakeTimers();
  sessionStorage.clear();
});
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount());
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe("issuance progress", () => {
  it("keeps polling a dismissed paid order until issued without payment verification", async () => {
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(response(order("issuing")))
      .mockResolvedValue(response(order("issued")));
    const { progress, onIssued } = mountProgress(order("funded"));
    progress.dismissed.value = true;
    await vi.advanceTimersByTimeAsync(20000);
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch.mock.calls.every(([, init]) => !init?.method)).toBe(true);
    expect(onIssued).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(60000);
    expect(fetch).toHaveBeenCalledTimes(2);
  });
  it("recognizes completion returned by verification exactly once", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(response(order("issued")));
    const { progress, onIssued } = mountProgress(order("awaiting_payment"));
    await progress.verify();
    progress.current.value = order("issued");
    expect(onIssued).toHaveBeenCalledTimes(1);
  });
  it("progresses through verification, funding, and queued issuance", async () => {
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(response(order("awaiting_payment")))
      .mockResolvedValueOnce(response(order("funded")))
      .mockResolvedValue(response(order("issued")));
    const { onIssued } = mountProgress(order("awaiting_payment"));
    await vi.advanceTimersByTimeAsync(20000);
    expect(fetch.mock.calls.map(([, init]) => init?.method ?? "GET")).toEqual([
      "GET",
      "POST",
      "GET",
    ]);
    expect(onIssued).toHaveBeenCalledTimes(1);
  });
  it("respects Retry-After even for manual refresh and recovers", async () => {
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(
        response(order("funded"), 429, { "Retry-After": "45" }),
      )
      .mockResolvedValue(response(order("issued")));
    const { progress, onIssued } = mountProgress(order("funded"));
    await vi.advanceTimersByTimeAsync(10000);
    await progress.refresh();
    await vi.advanceTimersByTimeAsync(44000);
    expect(fetch).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1000);
    expect(onIssued).toHaveBeenCalledTimes(1);
  });
  it("backs off network failures without clearing the order", async () => {
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockRejectedValueOnce(new Error("offline"))
      .mockResolvedValue(response(order("issued")));
    const { progress, onIssued } = mountProgress(order("funded"));
    await vi.advanceTimersByTimeAsync(10000);
    expect(progress.current.value?.status).toBe("funded");
    expect(progress.error.value).toBeTruthy();
    await vi.advanceTimersByTimeAsync(10000);
    expect(onIssued).toHaveBeenCalledTimes(1);
    expect(fetch).toHaveBeenCalledTimes(2);
  });
  it("stops attention polling until a guarded retry is pending", async () => {
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValue(response(order("issued")));
    const { progress } = mountProgress(order("issuance_attention"));
    await vi.advanceTimersByTimeAsync(30000);
    expect(fetch).not.toHaveBeenCalled();
    progress.current.value = {
      ...order("issuance_attention"),
      retry: { ...projection.retry, pending: true },
    };
    await vi.advanceTimersByTimeAsync(10000);
    expect(fetch).toHaveBeenCalledTimes(1);
  });
  it.each(["expired", "cancelled"])(
    "does not poll %s orders",
    async (status) => {
      const fetch = vi.spyOn(globalThis, "fetch");
      mountProgress(order(status));
      await vi.advanceTimersByTimeAsync(60000);
      expect(fetch).not.toHaveBeenCalled();
    },
  );
  it("restores a dismissed completed order using authorized GET without persisting credentials", async () => {
    sessionStorage.setItem(
      "recovery",
      JSON.stringify({
        reference: "ORDER-1",
        url: projection.actions.show,
        dismissed: true,
      }),
    );
    const issued = { ...order("issued"), guest_access_token: "private-token" };
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValue(response(issued));
    const { progress, onIssued } = mountProgress(null, "recovery");
    await vi.advanceTimersByTimeAsync(0);
    expect(progress.dismissed.value).toBe(true);
    expect(onIssued).toHaveBeenCalledTimes(1);
    expect(fetch).toHaveBeenCalledWith(
      projection.actions.show,
      expect.objectContaining({ credentials: "same-origin" }),
    );
    expect(sessionStorage.getItem("recovery")).not.toContain("private-token");
  });
  it("discards an inaccessible recovery reference", async () => {
    sessionStorage.setItem(
      "recovery",
      JSON.stringify({
        reference: "ORDER-1",
        url: projection.actions.show,
        dismissed: true,
      }),
    );
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      response(order("funded"), 404),
    );
    const { progress } = mountProgress(null, "recovery");
    await vi.advanceTimersByTimeAsync(0);
    expect(progress.current.value).toBeNull();
    expect(sessionStorage.getItem("recovery")).toBeNull();
  });
  it("shows delayed status and prevents overlapping requests", async () => {
    let resolve!: (r: Response) => void;
    const fetch = vi.spyOn(globalThis, "fetch").mockImplementation(
      () =>
        new Promise((r) => {
          resolve = r;
        }),
    );
    const { progress } = mountProgress(null);
    progress.current.value = order("funded");
    await vi.advanceTimersByTimeAsync(30000);
    expect(progress.delayed.value).toBe(true);
    await progress.refresh();
    expect(fetch).toHaveBeenCalledTimes(1);
    resolve(response(order("issued")));
    await vi.advanceTimersByTimeAsync(0);
  });
});
