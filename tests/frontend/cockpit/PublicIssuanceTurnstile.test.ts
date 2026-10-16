import { mount } from "@vue/test-utils";
import { afterEach, expect, it, vi } from "vitest";
import PublicIssuanceTurnstile from "../../../resources/js/cockpit/components/PublicIssuanceTurnstile.vue";

afterEach(() => {
  vi.unstubAllGlobals();
});

it("renders a public issuance challenge and clears its token on reset", () => {
  let verified: ((token: string) => void) | undefined;
  const reset = vi.fn();
  const remove = vi.fn();

  vi.stubGlobal("turnstile", {
    render: vi.fn((_element: HTMLElement, options: Record<string, unknown>) => {
      verified = options.callback as (token: string) => void;
      expect(options.sitekey).toBe("public-site-key");
      expect(options.action).toBe("public_auto_generate");

      return "widget-id";
    }),
    reset,
    remove,
  });

  const wrapper = mount(PublicIssuanceTurnstile, {
    props: { siteKey: "public-site-key" },
  });

  verified?.("visitor-token");
  expect(wrapper.emitted("verified")?.[0]).toEqual(["visitor-token"]);

  wrapper.vm.reset();
  expect(wrapper.emitted("verified")?.[1]).toEqual([""]);
  expect(reset).toHaveBeenCalledWith("widget-id");

  wrapper.unmount();
  expect(remove).toHaveBeenCalledWith("widget-id");
});
