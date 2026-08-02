import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import MobileVerification from "../../resources/js/pages/x-change/onboarding/MobileVerification.vue";

vi.mock("@inertiajs/vue3", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@inertiajs/vue3")>()),
  Head: { template: "<div><slot /></div>" },
}));

describe("mobile verification onboarding", () => {
  it("explains the protected action boundary without exposing a local code", () => {
    const wrapper = mount(MobileVerification, {
      props: {
        mobile: "63••••••1987",
        verified: false,
        challenge: {
          status: "pending",
          attempts: 0,
          expires_at: "2026-07-23T20:00:00+08:00",
        },
      },
    });

    expect(
      wrapper.get('[data-testid="mobile-verification-page"]').text(),
    ).toContain("Verify your mobile");
    expect(wrapper.text()).toContain(
      "campaign authorization and account funding",
    );
    expect(wrapper.text()).toContain("63••••••1987");
    expect(wrapper.text()).not.toContain("000000");
    expect(wrapper.text()).not.toContain("Local simulation code");
    expect(wrapper.text()).toContain("Verification proves mobile ownership");
    expect(wrapper.text()).not.toContain("639173011987");
  });

  it("renders a verified completion state without the OTP form", () => {
    const wrapper = mount(MobileVerification, {
      props: {
        mobile: "63••••••1987",
        verified: true,
      },
    });

    expect(wrapper.text()).toContain("Verified");
    expect(wrapper.text()).toContain(
      "can now be used for protected x-change actions",
    );
    expect(
      wrapper.find('[data-testid="mobile-verification-code"]').exists(),
    ).toBe(false);
  });

  it("renders campaign officer authorization copy when auth intent is present", () => {
    const wrapper = mount(MobileVerification, {
      props: {
        mobile: "63••••••1987",
        verified: false,
        auth_intent: {
          type: "campaign_authorization",
          code: "AUTH1",
          title: "Officer authorization required",
          description:
            "Sign in with the campaign officer account authorized to approve this worksheet.",
          intended_url: "/x/claim/AUTH1",
        },
      },
    });

    expect(wrapper.text()).toContain("Officer authorization");
    expect(wrapper.text()).toContain("Verify your officer mobile");
    expect(wrapper.text()).toContain(
      "Campaign authorization requires a verified officer mobile",
    );
  });
});
