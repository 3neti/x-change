import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
  type Ref,
} from "vue";
import type { CockpitOnDemandIssuanceFundingProjection as Projection } from "../types";

export function useIssuanceProgress(
  current: Ref<Projection | null>,
  options: {
    onIssued?: (projection: Projection) => void;
    storageKey?: string;
  } = {},
) {
  const busy = ref(false);
  const error = ref<string | null>(null);
  const dismissed = ref(false);
  const delayed = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;
  let delayTimer: ReturnType<typeof setTimeout> | null = null;
  let disposed = false;
  let failures = 0;
  let notBefore = 0;
  let emittedReference: string | null = null;
  let paidReference: string | null = null;
  const terminal = computed(() =>
    ["issued", "cancelled", "expired"].includes(current.value?.status ?? ""),
  );
  const pending = computed(
    () =>
      !!current.value &&
      !terminal.value &&
      (["funded", "issuing"].includes(current.value.status) ||
        current.value.retry.pending ||
        (current.value.monitor.enabled && current.value.monitor.eligible)),
  );

  function storage(value?: string | null): string | null {
    if (!options.storageKey) return null;
    try {
      if (value === null) sessionStorage.removeItem(options.storageKey);
      else if (value !== undefined)
        sessionStorage.setItem(options.storageKey, value);
      return sessionStorage.getItem(options.storageKey);
    } catch {
      return null;
    }
  }

  function remember(): void {
    const order = current.value;
    if (!order) return;
    if (["cancelled", "expired"].includes(order.status)) {
      storage(null);
      return;
    }
    const url = new URL(order.actions.show, window.location.origin);
    if (url.origin !== window.location.origin || url.search || url.hash) return;
    storage(
      JSON.stringify({
        reference: order.order.reference,
        url: url.pathname,
        dismissed: dismissed.value,
      }),
    );
  }

  function schedule(): void {
    if (timer) clearTimeout(timer);
    timer = null;
    if (disposed || !pending.value) return;
    const interval = Math.max(
      5000,
      current.value?.monitor.interval_milliseconds ?? 10000,
    );
    timer = setTimeout(
      () => void refresh(),
      Math.max(interval, notBefore - Date.now()),
    );
  }

  async function request(
    url: string,
    init: RequestInit = {},
  ): Promise<Response> {
    if (busy.value || Date.now() < notBefore)
      throw new Error(
        "Status checking will resume shortly. Your order is still active.",
      );
    busy.value = true;
    try {
      const response = await fetch(url, {
        credentials: "same-origin",
        ...init,
      });
      if (response.status === 429) {
        const retry = response.headers?.get("Retry-After");
        const seconds = retry ? Number(retry) : NaN;
        const until = Number.isFinite(seconds)
          ? Date.now() + seconds * 1000
          : Date.parse(retry ?? "");
        notBefore = Math.max(
          Date.now() + 5000,
          Number.isFinite(until) ? until : Date.now() + 30000,
        );
        throw new Error(
          "Status checking will resume shortly. Your order is still active.",
        );
      }
      if (response.status >= 500)
        throw new Error(
          "Status is temporarily unavailable. We will keep checking. Do not pay again.",
        );
      failures = 0;
      return response;
    } catch (exception) {
      if (Date.now() >= notBefore)
        notBefore = Date.now() + Math.min(60000, 5000 * 2 ** failures++);
      throw exception;
    } finally {
      busy.value = false;
      schedule();
    }
  }

  function headers(): Record<string, string> {
    const token = current.value?.guest_access_token;
    return {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(token ? { "X-XChange-Guest-Order-Token": token } : {}),
    };
  }

  async function verify(): Promise<void> {
    if (!current.value?.monitor.eligible || busy.value) return;
    const reference = current.value.order.reference;
    const csrf = document.querySelector<HTMLMetaElement>(
      'meta[name="csrf-token"]',
    )?.content;
    try {
      const response = await request(current.value.actions.verify, {
        method: "POST",
        headers: { ...headers(), ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}) },
      });
      if (!response.ok)
        throw new Error(
          "The payment check could not be started. Do not pay again.",
        );
      const next = (await response.json()) as Projection;
      if (!disposed && current.value?.order.reference === reference)
        current.value = next;
    } catch (exception) {
      error.value =
        exception instanceof Error
          ? exception.message
          : "Payment checking will retry shortly.";
    } finally {
      schedule();
    }
  }

  async function refresh(): Promise<void> {
    if (!current.value || busy.value || disposed) {
      schedule();
      return;
    }
    const reference = current.value.order.reference;
    try {
      const response = await request(current.value.actions.show, {
        headers: headers(),
      });
      if (!response.ok)
        throw new Error("Status could not be refreshed. Do not pay again.");
      const next = (await response.json()) as Projection;
      if (disposed || current.value?.order.reference !== reference) return;
      current.value = next;
      error.value = null;
      if (next.monitor.enabled && next.monitor.eligible) await verify();
    } catch (exception) {
      error.value =
        exception instanceof Error
          ? exception.message
          : "Status could not be refreshed. We will keep checking.";
    } finally {
      schedule();
    }
  }

  watch(
    current,
    (next, previous) => {
      if (!next) storage(null);
      if (next?.order.reference !== previous?.order.reference) {
        failures = 0;
        notBefore = 0;
        try {
          const saved = JSON.parse(storage() ?? "null");
          dismissed.value =
            saved?.reference === next?.order.reference &&
            saved?.dismissed === true;
        } catch {
          dismissed.value = false;
        }
      }
      const paid =
        next && ["funded", "issuing"].includes(next.status)
          ? next.order.reference
          : null;
      if (paid !== paidReference) {
        paidReference = paid;
        delayed.value = false;
        if (delayTimer) clearTimeout(delayTimer);
        if (paid)
          delayTimer = setTimeout(() => {
            delayed.value = true;
          }, 30000);
      }
      remember();
      if (
        next?.status === "issued" &&
        emittedReference !== next.order.reference
      ) {
        emittedReference = next.order.reference;
        options.onIssued?.(next);
      }
      schedule();
    },
    { flush: "sync" },
  );
  watch(dismissed, remember);

  onMounted(async () => {
    try {
      const saved = JSON.parse(storage() ?? "null");
      if (
        saved &&
        (!current.value || current.value.order.reference === saved.reference)
      ) {
        dismissed.value = saved.dismissed === true;
        if (
          !current.value &&
          typeof saved.url === "string" &&
          /^\/x\/(cockpit\/quick-generate|auto-generate)\/funding-orders\/[^/?#]+$/.test(
            saved.url,
          )
        ) {
          const response = await request(saved.url, { headers: headers() });
          if (response.ok) {
            const next = (await response.json()) as Projection;
            if (
              !disposed &&
              !current.value &&
              next.order.reference === saved.reference
            )
              current.value = next;
          } else if ([401, 403, 404, 410].includes(response.status))
            storage(null);
        }
      }
    } catch {
      /* Storage and recovery failures must not prevent normal issuance. */
    }
    if (current.value) {
      if (
        !paidReference &&
        ["funded", "issuing"].includes(current.value.status)
      ) {
        paidReference = current.value.order.reference;
        delayTimer = setTimeout(() => {
          delayed.value = true;
        }, 30000);
      }
      remember();
      if (
        current.value.status === "issued" &&
        emittedReference !== current.value.order.reference
      ) {
        emittedReference = current.value.order.reference;
        options.onIssued?.(current.value);
      }
    }
    schedule();
  });
  onBeforeUnmount(() => {
    disposed = true;
    if (timer) clearTimeout(timer);
    if (delayTimer) clearTimeout(delayTimer);
  });
  return { current, busy, error, dismissed, delayed, refresh, verify, request };
}

export type IssuanceProgress = ReturnType<typeof useIssuanceProgress>;
