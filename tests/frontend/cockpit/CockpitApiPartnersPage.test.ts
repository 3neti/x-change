import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import ApiPartners from "../../../resources/js/cockpit/pages/ApiPartners.vue";

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<span />" },
  router: { post: vi.fn(), reload: vi.fn() },
}));

vi.mock("../../../resources/js/cockpit/layouts/CockpitLayout.vue", () => ({
  default: { template: "<main><slot /></main>" },
}));

const partnerApi = {
  api_enabled: true,
  production_governance: "maker_checker_required",
  can_create_sandbox: true,
  can_suspend: true,
  can_revoke: true,
  scopes: [
    { scope: "capabilities:read", description: "Inspect capabilities." },
  ],
  rails: ["automatic", "INSTAPAY"],
  issuers: [
    { id: "5", name: "Amelia Hurtado", identity: "Mobile ending 3656" },
  ],
  clients: [],
};

describe("API Partners Cockpit", () => {
  it("renders explicit sandbox provisioning and fail-closed production governance", async () => {
    const wrapper = mount(ApiPartners, {
      props: {
        partnerApi,
        partnerApiStoreUrl: "/x/cockpit/api-partners/clients",
        csrfToken: "token",
      },
    });

    expect(wrapper.text()).toContain("API Partners");
    expect(wrapper.text()).toContain("Provision Sandbox Client");
    expect(wrapper.text()).toContain(
      "Independent maker-checker approval is required",
    );

    await wrapper.get("button").trigger("click");
    expect(wrapper.text()).toContain("shown once and cannot be recovered");
    expect(wrapper.html()).not.toContain("Provision Production Client");
  });
});
