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
  can_request_production: false,
  can_approve_production: false,
  can_activate_production: false,
  scopes: [
    { scope: "capabilities:read", description: "Inspect capabilities." },
  ],
  rails: ["automatic", "INSTAPAY"],
  issuers: [
    { id: "5", name: "Amelia Hurtado", identity: "Mobile ending 3656" },
  ],
  clients: [],
  production_mandates: [],
};

describe("API Partners Cockpit", () => {
  it("renders explicit sandbox provisioning and fail-closed production governance", async () => {
    const wrapper = mount(ApiPartners, {
      props: {
        partnerApi,
        partnerApiStoreUrl: "/x/cockpit/api-partners/clients",
        partnerApiProductionStoreUrl: "/x/cockpit/api-partners/production-mandates",
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

  it("renders immutable production mandates and checker-owned transitions", async () => {
    const wrapper = mount(ApiPartners, {
      props: {
        partnerApi: {
          ...partnerApi,
          can_create_sandbox: false,
          can_request_production: true,
          can_approve_production: true,
          production_mandates: [
            {
              reference: "01TEST",
              name: "Saras Production",
              status: "awaiting_approval",
              snapshot_hash: "a".repeat(64),
              scopes: ["capabilities:read"],
              issuer: { name: "Amelia Hurtado", identity: "Mobile ending 3656" },
              submitted_at: "2026-08-16T00:00:00Z",
              actions: { approve: "/approve", activate: "/activate" },
            },
          ],
        },
        partnerApiStoreUrl: "/x/cockpit/api-partners/clients",
        partnerApiProductionStoreUrl: "/x/cockpit/api-partners/production-mandates",
        csrfToken: "token",
      },
    });

    expect(wrapper.text()).toContain("Request Production Client");
    expect(wrapper.text()).toContain("Saras Production");
    expect(wrapper.text()).toContain("Approve");
    expect(wrapper.text()).not.toContain("Issue Credentials");
  });
});
