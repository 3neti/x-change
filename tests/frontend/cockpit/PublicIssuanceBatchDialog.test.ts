import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import PublicIssuanceBatchDialog from "../../../resources/js/cockpit/components/PublicIssuanceBatchDialog.vue";

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
});
