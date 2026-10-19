import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import { h } from "vue";
import Presentation from "@/pages/form-flow/core/components/FormFlowPresentation.vue";
import Actions from "@/pages/form-flow/core/components/FormFlowActions.vue";
import Notice from "@/pages/form-flow/core/components/FormFlowNotice.vue";
import {
  actionLabel,
  presentationForScreen,
  type FormFlowPresentation,
} from "@/pages/form-flow/core/components/formFlowPresentation";

const profile = (): FormFlowPresentation => ({
  schema: "form-flow.presentation.v1",
  brand: {
    name: "Example",
    logo: "/example.svg",
    placement: "center",
    logo_height: 40,
    logo_max_width: 140,
  },
  layout: {
    density: "compact",
    capture_surface: "contained",
    scrolling: "when_needed",
  },
  actions: {
    placement: "inline",
    primary_label: "Continue",
    secondary_label: "Back",
    reserve_content_space: true,
  },
  progress: { show: false },
  diagnostics: { show_versions: false },
});

describe("portable presentation contract", () => {
  it("renders tokens branding notices and semantic action copy from one profile", () => {
    const p = profile();
    p.tokens = {
      content_width: "wide",
      spacing: "spacious",
      typography: "large",
      control_size: "large",
    };
    p.notices = {
      attention: {
        background: "#112233",
        foreground: "#ffffff",
        border: "#445566",
        icon: "shield-check",
      },
    };
    p.actions.roles = {
      capture: { label: "Take photo", processing_label: "Taking photo…" },
    };
    const wrapper = mount(Presentation, {
      props: { presentation: p },
      slots: {
        default: () => [
          h(Notice, { role: "attention" }, () => "Check this"),
          h(Actions, {
            primaryRole: "capture",
            primaryLabel: "Capture Photo",
            showSecondary: false,
          }),
        ],
      },
    });
    expect(
      wrapper
        .get('[data-testid="form-flow-presentation-logo"]')
        .attributes("style"),
    ).toContain("height: 40px");
    expect(wrapper.attributes("style")).toContain(
      "--flow-content-width: 42rem",
    );
    expect(wrapper.attributes("style")).toContain(
      "--flow-control-size: 3.25rem",
    );
    expect(
      wrapper.get('[data-flow-notice="attention"]').attributes("style"),
    ).toContain("rgb(17, 34, 51)");
    expect(wrapper.get('[data-claim-icon-name="shield-check"]').exists()).toBe(
      true,
    );
    expect(wrapper.text()).toContain("Take photo");
    wrapper.unmount();
  });
  it("does not let navigation labels replace meaningful operations", () => {
    const p = profile();
    for (const role of ["capture", "verify", "retry", "confirm_claim"] as const)
      expect(actionLabel(p, role, "Accurate operation")).toBe(
        "Accurate operation",
      );
    expect(actionLabel(p, "continue", "Next")).toBe("Continue");
    p.actions.roles = {
      verify: { label: "Verify code", processing_label: "Verifying code…" },
    };
    expect(actionLabel(p, "verify", "Verify OTP")).toBe("Verify code");
    expect(actionLabel(p, "verify", "Please wait...", true)).toBe(
      "Verifying code…",
    );
  });
  it("resolves nested screen settings without mutation or overriding frozen step settings", () => {
    const p = profile();
    p.actions.roles = {
      verify: { label: "Verify", processing_label: "Working" },
    };
    p.screens = {
      confirmation: {
        tokens: { content_width: "wide" },
        actions: { roles: { verify: { label: "Verify claim" } } },
      },
    };
    const result = presentationForScreen(p, "confirmation")!;
    expect(result.actions.roles?.verify).toEqual({
      label: "Verify claim",
      processing_label: "Working",
    });
    expect(p.actions.roles.verify.label).toBe("Verify");
    result.tokens = { content_width: "narrow" };
    expect(
      presentationForScreen(result, "confirmation")?.tokens?.content_width,
    ).toBe("narrow");
  });
  it("applies a scoped palette in standalone hosts without changing document appearance", () => {
    const p = profile();
    p.appearance = {
      mode: "dark",
      dark: {
        canvas: "#112233",
        surface: "#223344",
        foreground: "#ffffff",
        muted: "#cccccc",
        border: "#445566",
        accent: "#ffffff",
        accent_foreground: "#112233",
      },
      light: {} as any,
    };
    const previous = document.documentElement.className;
    const wrapper = mount(Presentation, { props: { presentation: p } });
    expect(wrapper.attributes("style")).toContain("--background: #112233");
    expect(document.documentElement.className).toBe(previous);
    wrapper.unmount();
  });
});
