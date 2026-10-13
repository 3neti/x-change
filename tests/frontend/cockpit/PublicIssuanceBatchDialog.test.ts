import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/vue3", async () => {
  const actual = await vi.importActual<typeof import("@inertiajs/vue3")>("@inertiajs/vue3");

  return {
    ...actual,
    Head: { template: "<span />" },
    Link: { props: ["href"], template: "<a :href=\"href\"><slot /></a>" },
  };
});

import PublicIssuanceBatchDialog from "../../../resources/js/cockpit/components/PublicIssuanceBatchDialog.vue";
import BatchPayCodes from "../../../resources/js/pages/x-change/public/BatchPayCodes.vue";
import BatchPayCodeDetail from "../../../resources/js/pages/x-change/public/BatchPayCodeDetail.vue";

describe("PublicIssuanceBatchDialog", () => {
  it("exposes the batch creation action when package endpoints are supplied", () => {
    const wrapper = mount(PublicIssuanceBatchDialog, {
      props: {
        endpoints: {
          preview: "/x/auto-generate/batches/preview",
          create: "/x/auto-generate/batches",
          fund: "/x/auto-generate/batches/__REFERENCE__/fund",
        },
      },
    });

    expect(
      wrapper.get('[data-testid="public-auto-generate-batch-action"]').text(),
    ).toContain("Create many now");
  });

  it("exposes file, drag-and-drop, and pasted-row intake controls", async () => {
    const wrapper = mount(PublicIssuanceBatchDialog, {
      props: {
        endpoints: {
          preview: "/x/auto-generate/batches/preview",
          create: "/x/auto-generate/batches",
          fund: "/x/auto-generate/batches/__REFERENCE__/fund",
        },
      },
    });

    await wrapper.get('[data-testid="public-auto-generate-batch-action"]').trigger("click");
    expect(document.body.textContent).toContain("Drop your recipient list here");
    expect(document.body.textContent).toContain("CSV · XLS · XLSX · or copied spreadsheet rows");
  });

  it("shows the Pay Code value, issuance fee, and total due after review", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        headers: ["name", "mobile", "amount"],
        mapping: { name: "name", mobile: "mobile", amount: "amount" },
        items: [{ source_row: 2, status: "valid", amount_minor: 2500, payload: { name: "Lester", mobile: "09173011987" } }],
        total_minor: 2500,
        pay_code_value_minor: 2500,
        issuance_fee_minor: 500,
        total_due_minor: 3000,
        currency: "PHP",
        errors: [],
        can_create: true,
      }),
    });
    vi.stubGlobal("fetch", fetchMock);
    const wrapper = mount(PublicIssuanceBatchDialog, {
      props: {
        endpoints: {
          preview: "/x/auto-generate/batches/preview",
          create: "/x/auto-generate/batches",
          fund: "/x/auto-generate/batches/__REFERENCE__/fund",
        },
      },
    });

    await wrapper.get('[data-testid="public-auto-generate-batch-action"]').trigger("click");
    const dropzone = document.body.querySelector('[data-testid="batch-source-dropzone"]');
    const pasteEvent = new Event("paste", { bubbles: true });
    Object.defineProperty(pasteEvent, "clipboardData", { value: { getData: () => "name,mobile,amount\nLester,09173011987,25" } });
    dropzone?.dispatchEvent(pasteEvent);
    await new Promise((resolve) => window.setTimeout(resolve, 0));
    document.body.querySelector('[data-testid="batch-review-button"]')?.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    await new Promise((resolve) => window.setTimeout(resolve, 0));

    expect(document.body.textContent).toContain("Issuance fee");
    expect(document.body.textContent).toContain("₱5.00");
    expect(document.body.textContent).toContain("Total to pay");
    expect(document.body.textContent).toContain("₱30.00");
  });

  it("loads only the token-authorized batch rows into the shared Pay Code table", async () => {
    sessionStorage.setItem("x-change.batch-token:BATCH-1", "token-1");
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        reference: "BATCH-1",
        status: "issued",
        item_count: 1,
        valid_item_count: 1,
        total_minor: 2500,
        expires_at: null,
        funded_at: null,
        issued_at: "2026-10-13T00:00:00+08:00",
        items: [
          {
            ordinal: 1,
            source_row: 2,
            name: "Lester Hurtado",
            mobile: "09173011987",
            email: null,
            amount_minor: 2500,
            pay_code: "PC-1",
            claim_url: "/x/claim/PC-1",
            status: "issued",
            lifecycle_status: "redeemed",
            redeemed_at: "2026-10-13T01:00:00+08:00",
            claim_summary: {
              status: "succeeded",
              claimed_at: "2026-10-13T01:00:00+08:00",
              amount_minor: 2500,
              currency: "PHP",
            },
            issued_at: "2026-10-13T00:00:00+08:00",
            failure_message: null,
          },
        ],
      }),
    });
    vi.stubGlobal("fetch", fetchMock);

    const wrapper = mount(BatchPayCodes, {
      props: {
        batch_reference: "BATCH-1",
        batch_endpoint: "/x/auto-generate/batches/BATCH-1",
        download_endpoint: "/x/auto-generate/batches/BATCH-1/download",
        detail_url_template: "/x/auto-generate/batches/BATCH-1/pay-codes/__CODE__",
        recovery_request_endpoint: "/x/auto-generate/batches/BATCH-1/recover",
        recovery_verify_endpoint: "/x/auto-generate/batches/BATCH-1/recover/verify",
        auto_generate_url: "/x/auto-generate",
      },
    });

    await new Promise((resolve) => window.setTimeout(resolve, 0));

    expect(fetchMock).toHaveBeenCalledWith(
      "/x/auto-generate/batches/BATCH-1",
      expect.objectContaining({
        headers: expect.objectContaining({
          "X-Issuance-Batch-Token": "token-1",
        }),
      }),
    );
    expect(wrapper.text()).toContain("Lester Hurtado");
    expect(wrapper.text()).toContain("PC-1");
    expect(wrapper.text()).toContain("Disbursement");
    expect(wrapper.text()).toContain("Redeemable");
    expect(wrapper.text()).toContain("1 / 1");
    expect(wrapper.text()).toContain("redeemed");
    expect(wrapper.get('[data-testid="batch-workspace-recovery-toggle"]').text()).toContain(
      "Need to recover this workspace?",
    );
    await wrapper.get('[data-testid="batch-workspace-recovery-toggle"]').trigger("click");
    expect(wrapper.get('input[placeholder="09XXXXXXXXX"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="cockpit-pay-code-row-action-link"]').attributes("href")).toBe(
      "/x/auto-generate/batches/BATCH-1/pay-codes/PC-1",
    );
  });

  it("loads a token-authorized shared detail view", async () => {
    sessionStorage.setItem("x-change.batch-token:BATCH-1", "token-1");
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        context: { code: "PC-1" },
        read_model: { code: "PC-1", voucher: { summary: { code: "PC-1", status: "issued" } } },
      }),
    }));

    const wrapper = mount(BatchPayCodeDetail, {
      props: {
        batch_reference: "BATCH-1",
        code: "PC-1",
        detail_endpoint: "/x/auto-generate/batches/BATCH-1/pay-codes/PC-1/data",
        batch_pay_codes_url: "/x/auto-generate/batches/BATCH-1/pay-codes",
      },
    });

    await new Promise((resolve) => window.setTimeout(resolve, 0));

    expect(fetch).toHaveBeenCalledWith(
      "/x/auto-generate/batches/BATCH-1/pay-codes/PC-1/data",
      expect.objectContaining({
        headers: expect.objectContaining({ "X-Issuance-Batch-Token": "token-1" }),
      }),
    );
  });

  it("explains when a direct detail link needs workspace recovery", async () => {
    sessionStorage.setItem("x-change.batch-token:BATCH-1", "expired-token");
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false,
      status: 404,
      json: async () => ({}),
    }));

    const wrapper = mount(BatchPayCodeDetail, {
      props: {
        batch_reference: "BATCH-1",
        code: "BRHL",
        detail_endpoint: "/x/auto-generate/batches/BATCH-1/pay-codes/BRHL/data",
        batch_pay_codes_url: "/x/auto-generate/batches/BATCH-1/pay-codes",
      },
    });

    await new Promise((resolve) => window.setTimeout(resolve, 0));

    expect(wrapper.get('[data-testid="batch-pay-code-detail-access-error"]').text()).toContain(
      "workspace has expired",
    );
    expect(wrapper.get('a[href="/x/auto-generate/batches/BATCH-1/pay-codes"]').text()).toContain(
      "Open batch workspace",
    );
  });
});
