import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Invitation from "../../../resources/js/pages/x-change/provisioning/Invitation.vue";

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div />" },
  Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
  router: { post },
}));

const invitation = {
  profile: "treasury_maker",
  label: "Treasury Maker",
  purpose: "Accept responsibility for governed Treasury requests.",
  status: "offered",
  required_evidence: ["name", "email", "mobile", "otp", "responsibility_attestation"],
  expires_at: "2026-08-22T01:00:00Z",
  authenticated: true,
  can_accept: true,
  accept_url: "/x/provisioning/secret",
  login_url: "/login",
};

describe("Provisioning invitation", () => {
  it("requires an explicit responsibility attestation before acceptance", async () => {
    post.mockReset();
    const wrapper = mount(Invitation, { props: { invitation } });
    const button = wrapper.get("button");

    expect(wrapper.text()).toContain("Governed Provisioning");
    expect(wrapper.text()).toContain("Evidence required");
    expect(button.attributes("disabled")).toBeDefined();

    await wrapper.get('input[type="checkbox"]').setValue(true);
    await button.trigger("click");

    expect(post).toHaveBeenCalledWith(
      "/x/provisioning/secret",
      { responsibility_attestation: true },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("explains that accepted authority still awaits its controlled activation gate", () => {
    const wrapper = mount(Invitation, {
      props: {
        invitation: { ...invitation, status: "activation_pending", can_accept: false },
      },
    });

    expect(wrapper.text()).toContain("Invitation accepted");
    expect(wrapper.text()).toContain("controlled activation gate");
    expect(wrapper.find("button").exists()).toBe(false);
  });

  it("directs an unauthenticated candidate to sign in without exposing an acceptance action", () => {
    const wrapper = mount(Invitation, {
      props: {
        invitation: { ...invitation, authenticated: false, can_accept: false },
      },
    });

    expect(wrapper.get("a").attributes("href")).toBe("/login");
    expect(wrapper.text()).toContain("Sign In Or Create Account");
  });
});
