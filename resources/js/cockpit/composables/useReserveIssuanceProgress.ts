import { computed, onUnmounted, ref } from "vue";

export type ReserveIssuance = {
  reference: string;
  status: "processing" | "needs_review" | "failed" | "issued";
  amount_minor: number;
  currency: string;
  pay_code: string | null;
  status_url: string;
  detail_url: string | null;
};

function isIssuance(value: unknown): value is ReserveIssuance {
  if (!value || typeof value !== "object") return false;
  const item = value as Record<string, unknown>;
  return typeof item.reference === "string" && typeof item.status_url === "string" &&
    ["processing", "needs_review", "failed", "issued"].includes(String(item.status));
}

export function useReserveIssuanceProgress(indexUrl: string | null | undefined) {
  const issuance = ref<ReserveIssuance | null>(null);
  const checking = ref(Boolean(indexUrl));
  const error = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;
  let disposed = false;
  const blocking = computed(() => checking.value || error.value ||
    issuance.value?.status === "processing" || issuance.value?.status === "needs_review");

  function schedule(): void {
    if (!disposed && issuance.value?.status === "processing") {
      timer = setTimeout(() => { void refresh(); }, 5000);
    }
  }

  async function refresh(): Promise<void> {
    if (!issuance.value?.status_url || disposed) return;
    try {
      const response = await fetch(issuance.value.status_url, { credentials: "same-origin", headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Status unavailable");
      const body: unknown = await response.json();
      if (!isIssuance(body) || body.reference !== issuance.value.reference || body.status_url !== issuance.value.status_url) throw new Error("Invalid status");
      issuance.value = body;
      error.value = false;
    } catch {
      error.value = true;
    }
    schedule();
  }

  function track(value: ReserveIssuance): void {
    if (timer !== null) clearTimeout(timer);
    issuance.value = value;
    error.value = false;
    schedule();
  }

  async function restore(): Promise<void> {
    if (!indexUrl) return;
    checking.value = true;
    try {
      const response = await fetch(indexUrl, { credentials: "same-origin", headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Status unavailable");
      const body: unknown = await response.json();
      if (!body || typeof body !== "object" || !Array.isArray((body as { issuances?: unknown }).issuances)) throw new Error("Invalid status list");
      const items = (body as { issuances: unknown[] }).issuances;
      if (!items.every(isIssuance)) throw new Error("Invalid status list");
      const active = items.find((item) => item.status === "processing" || item.status === "needs_review");
      const latest = active ?? items.find((item) => item.status === "issued");
      if (latest) track(latest);
      error.value = false;
    } catch {
      error.value = true;
    } finally {
      checking.value = false;
    }
  }

  onUnmounted(() => { disposed = true; if (timer !== null) clearTimeout(timer); });
  return { issuance, checking, error, blocking, track, restore, refresh };
}
