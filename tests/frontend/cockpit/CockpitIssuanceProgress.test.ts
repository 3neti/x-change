import { mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Panel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import FundingDialog from "../../../resources/js/cockpit/components/CockpitOnDemandIssuanceFundingDialog.vue";
import IssuedDialog from "../../../resources/js/cockpit/components/CockpitIssuedPayCodeDialog.vue";
import ProgressCard from "../../../resources/js/cockpit/components/CockpitIssuanceProgressCard.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";
import { projection } from "./issuanceProjection";
vi.mock("@inertiajs/vue3", () => ({
  Link: { template: "<a><slot /></a>" },
  router: { reload: vi.fn() },
  usePage: () => ({ props: {} }),
}));
const wrappers: ReturnType<typeof mount>[] = [];
beforeEach(() => {
  vi.useFakeTimers();
  sessionStorage.clear();
});
afterEach(() => {
  wrappers.splice(0).forEach((w) => w.unmount());
  vi.useRealTimers();
  vi.restoreAllMocks();
});
function paid() {
  const value = structuredClone(projection);
  value.status = value.order.status = "funded";
  value.monitor.eligible = false;
  value.order.can_cancel = false;
  return value;
}
function issued() {
  const value = paid();
  value.status = value.order.status = "issued";
  value.order.voucher = {
    code: "READY100",
    amount: 100,
    currency: "PHP",
    claim_url: "https://example.test/x/claim/READY100",
    claim_qr: "",
    qr_artifacts: {} as any,
    share_card_url: "",
    detail_url: "/x/cockpit/pay-codes/READY100",
  };
  return value;
}
function panel() {
  const wrapper = mount(Panel, {
    props: {
      templates: cockpitQuickGenerateTemplates,
      activeOnDemandFundingOrder: paid(),
    },
    global: { stubs: { Teleport: true } },
  });
  wrappers.push(wrapper);
  return wrapper;
}
describe("issuance progress presentation", () => {
  it("keeps a dismissed order visible and presents completion without reopening the modal", async () => {
    const fetch = vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => issued(),
    } as Response);
    const wrapper = panel();
    await wrapper
      .get('[data-testid="on-demand-continue-working"]')
      .trigger("click");
    expect(wrapper.findComponent(FundingDialog).props("open")).toBe(false);
    expect(
      wrapper.get('[data-testid="issuance-progress-card"]').text(),
    ).toContain("Payment received");
    await vi.advanceTimersByTimeAsync(10000);
    expect(
      wrapper.get('[data-testid="issuance-progress-card"]').text(),
    ).toContain("Your Pay Code is ready");
    expect(wrapper.findComponent(IssuedDialog).props("open")).toBe(false);
    expect(wrapper.find('[data-testid="issuance-ready-notice"]').exists()).toBe(
      true,
    );
    expect(
      fetch.mock.calls
        .filter(([url]) => String(url).includes("funding-orders"))
        .every(([, init]) => !init?.method),
    ).toBe(true);
    wrapper.findComponent(ProgressCard).vm.$emit("view");
    await wrapper.vm.$nextTick();
    expect(wrapper.findComponent(IssuedDialog).props("open")).toBe(true);
    expect(wrapper.findComponent(IssuedDialog).props("code")).toBe("READY100");
  });
  it("opens the result automatically when the funding modal stays open", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => issued(),
    } as Response);
    const wrapper = panel();
    await vi.advanceTimersByTimeAsync(10000);
    expect(wrapper.findComponent(FundingDialog).props("open")).toBe(false);
    expect(wrapper.findComponent(IssuedDialog).props("open")).toBe(true);
  });
  it("resumes a dismissed paid order after reload without reopening its modal", async () => {
    sessionStorage.setItem(
      `x-change.issuance-progress:${window.location.pathname}`,
      JSON.stringify({
        reference: "ORDER-1",
        url: projection.actions.show,
        dismissed: true,
      }),
    );
    const wrapper = panel();
    await wrapper.vm.$nextTick();
    expect(wrapper.findComponent(FundingDialog).props("open")).toBe(false);
    expect(
      wrapper.find('[data-testid="issuance-progress-card"]').exists(),
    ).toBe(true);
  });
});

it("preserves batch downloads when background issuance completes", async () => {
  const batch = issued();
  batch.order.voucher = null;
  batch.batch = {
    reference: "BATCH-1",
    status: "issued",
    item_count: 2,
    valid_item_count: 2,
    total_minor: 20000,
    issued_item_count: 2,
    download_url: "/batch/download",
    workspace_url: "/batch",
    detail_url: "/batch/detail",
  };
  vi.spyOn(globalThis, "fetch").mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => batch,
  } as Response);
  const wrapper = panel();
  await wrapper
    .get('[data-testid="on-demand-continue-working"]')
    .trigger("click");
  await vi.advanceTimersByTimeAsync(10000);
  expect(
    wrapper.get('[data-testid="issuance-progress-card"]').text(),
  ).toContain("View batch");
  wrapper.findComponent(ProgressCard).vm.$emit("view");
  await wrapper.vm.$nextTick();
  expect(wrapper.findComponent(FundingDialog).props("open")).toBe(true);
  expect(wrapper.findComponent(FundingDialog).text()).toContain(
    "Download 2 Pay Codes",
  );
});
