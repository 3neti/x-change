import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Provisioning from "../../../resources/js/cockpit/pages/Provisioning.vue";

const { post, reload } = vi.hoisted(() => ({
  post: vi.fn(),
  reload: vi.fn(),
}));

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div />" },
  router: { post, reload },
}));

const provisioning = {
  capabilities: {
    view: true,
    request: true,
    approve: true,
    issue: true,
    activate: false,
    revoke: false,
  },
  stats: {
    vacant_seats: 1,
    awaiting_approval: 1,
    outstanding_offers: 1,
    activated: 0,
  },
  profiles: [
    {
      value: "treasury_maker",
      label: "Treasury Maker",
      description: "May prepare governed Treasury requests.",
    },
  ],
  seats: [
    {
      reference: "SEAT-1",
      key: "treasury-maker",
      label: "Treasury Maker",
      profile: "treasury_maker",
      profile_label: "Treasury Maker",
      required: true,
      status: "vacant",
      request_reference: null,
    },
  ],
  requests: [
    {
      reference: "REQ-1",
      profile: "treasury_maker",
      profile_label: "Treasury Maker",
      status: "awaiting_approval",
      commissioning: true,
      purpose: "Prepare the named Treasury maker.",
      required_evidence: ["name", "mobile", "otp", "responsibility_attestation"],
      snapshot_hash: "snapshot-hash",
      revision: 1,
      submitted_at: "2026-08-15T01:00:00Z",
      approved_at: null,
      offer: null,
      events: [],
      actions: {
        approve: "/approve",
        reject: "/reject",
        withdraw: "/withdraw",
        issue: "/issue",
      },
      created_at: "2026-08-15T01:00:00Z",
    },
  ],
};

describe("Cockpit Provisioning", () => {
  beforeEach(() => {
    post.mockReset();
    reload.mockReset();
  });

  it("renders vacant responsibilities and maker-checker authority requests", () => {
    const wrapper = mount(Provisioning, {
      props: {
        provisioning,
        provisioningStoreUrl: "/x/cockpit/provisioning/requests",
        csrfToken: "csrf",
      },
      global: {
        stubs: { CockpitLayout: { template: "<main><slot /></main>" } },
      },
    });

    expect(wrapper.text()).toContain("Provisioning");
    expect(wrapper.text()).toContain("Commissioning Seats");
    expect(wrapper.text()).toContain("Authority Requests");
    expect(wrapper.text()).toContain("Treasury Maker");
    expect(wrapper.text()).toContain("responsibility attestation");
    expect(wrapper.text()).toContain("Approve");
    expect(wrapper.text()).not.toContain("Activate Authority");
  });

  it("submits the immutable approval confirmation through the server action", async () => {
    const wrapper = mount(Provisioning, {
      props: {
        provisioning,
        provisioningStoreUrl: "/x/cockpit/provisioning/requests",
        csrfToken: "csrf",
      },
      global: {
        stubs: { CockpitLayout: { template: "<main><slot /></main>" } },
      },
    });

    const approveButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Approve"));
    expect(approveButton).toBeDefined();
    await approveButton!.trigger("click");

    expect(post).toHaveBeenCalledWith(
      "/approve",
      { confirm_snapshot: true },
      { preserveScroll: true },
    );
  });

  it("records an explicit reason before rejecting a request", async () => {
    const wrapper = mount(Provisioning, {
      props: {
        provisioning,
        provisioningStoreUrl: "/x/cockpit/provisioning/requests",
        csrfToken: "csrf",
      },
      global: {
        stubs: { CockpitLayout: { template: "<main><slot /></main>" } },
      },
    });
    const rejectButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Reject"));
    expect(rejectButton).toBeDefined();
    await rejectButton!.trigger("click");
    await wrapper.get('textarea[required]').setValue("Scope is incomplete.");
    await wrapper.get('form button[type="submit"]').trigger("submit");

    expect(post).toHaveBeenCalledWith(
      "/reject",
      { reason: "Scope is incomplete." },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("keeps the one-time invitation credential in browser memory only", async () => {
    const approved = structuredClone(provisioning);
    approved.requests[0].status = "approved";
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
          request_reference: "REQ-1",
          offer_reference: "OFFER-1",
          claim_url: "https://example.test/x/provisioning/secret",
          expires_at: "2026-08-22T01:00:00Z",
        }),
      }),
    );

    const wrapper = mount(Provisioning, {
      props: {
        provisioning: approved,
        provisioningStoreUrl: "/x/cockpit/provisioning/requests",
        csrfToken: "csrf",
      },
      global: {
        stubs: { CockpitLayout: { template: "<main><slot /></main>" } },
      },
    });

    const issueButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Issue Invitation"));
    expect(issueButton).toBeDefined();
    await issueButton!.trigger("click");
    await vi.waitFor(() => expect(wrapper.text()).toContain("Copy This Invitation Now"));

    expect(wrapper.text()).toContain("https://example.test/x/provisioning/secret");
    expect(fetch).toHaveBeenCalledWith(
      "/issue",
      expect.objectContaining({
        method: "POST",
        credentials: "same-origin",
      }),
    );
  });

  it("shows the immutable recipient Account and economics replacement to the checker", () => {
    const governed = structuredClone(provisioning);
    governed.profiles.push({
      value: "commercial_recipient_designation",
      label: "Commercial Recipient Account",
      description: "Binds an accepted recipient Account.",
    });
    governed.requests.push({
      ...structuredClone(provisioning.requests[0]),
      reference: "REQ-RECIPIENT",
      profile: "commercial_recipient_designation",
      profile_label: "Commercial Recipient Account",
      purpose: "Credit the accepted 3neti Account.",
      capabilities: [],
      activation_gate: "recipient_acceptance_and_economics_switch",
      recipient_designation: {
        counterparty_reference: "counterparty:3neti",
        commercial_role: "service_aggregator",
        agreement_reference: "agreement:commissioning:institution-3neti:v1",
        settlement_designation_reference: "designation:commissioning:3neti:v2",
        supersedes_designation_reference: "designation:commissioning:3neti:v1",
        settlement_disposition: "internal_account_credit",
        settlement_account_binding: "accepted_candidate_account",
        component_scope: ["inputs.fields.kyc", "inputs.fields.otp", "rider.splash"],
      },
    } as (typeof governed.requests)[number]);

    const wrapper = mount(Provisioning, {
      props: {
        provisioning: governed,
        provisioningStoreUrl: "/x/cockpit/provisioning/requests",
        csrfToken: "csrf",
      },
      global: {
        stubs: { CockpitLayout: { template: "<main><slot /></main>" } },
      },
    });

    expect(wrapper.text()).toContain("Recipient authority");
    expect(wrapper.text()).toContain("counterparty:3neti");
    expect(wrapper.text()).toContain("internal account credit · accepted candidate Account");
    expect(wrapper.text()).toContain("designation:commissioning:3neti:v1 → designation:commissioning:3neti:v2");
    expect(wrapper.text()).toContain("inputs.fields.kyc");
    expect(wrapper.text()).toContain("rider.splash");
  });
});
