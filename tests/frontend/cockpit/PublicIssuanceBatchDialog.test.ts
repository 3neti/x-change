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
  });
});
