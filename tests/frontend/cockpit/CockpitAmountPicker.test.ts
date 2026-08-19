import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import { nextTick } from "vue";
import NumericKeypad from "../../../resources/js/components/NumericKeypad.vue";
import CockpitAmountPicker from "../../../resources/js/cockpit/components/CockpitAmountPicker.vue";

describe("CockpitAmountPicker", () => {
  it("opens a calculator with familiar quick amounts and commits explicitly", async () => {
    const wrapper = mount(CockpitAmountPicker, {
      props: {
        modelValue: "",
      },
    });

    await wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .trigger("click");

    expect(wrapper.get('[role="dialog"]').text()).toContain("Pay Code Amount");
    expect(wrapper.get('[role="dialog"]').text()).toContain("₱100");
    expect(wrapper.get('[role="dialog"]').text()).toContain("₱10,000");

    const quickAmountButton = wrapper
      .findAll("button")
      .find((button) => button.text() === "₱1,000");

    expect(quickAmountButton).toBeDefined();
    await quickAmountButton!.trigger("click");

    expect(wrapper.emitted("update:modelValue")).toBeUndefined();

    const confirmButton = wrapper
      .findAll("button")
      .find((button) => button.text().includes("Use Amount"));

    expect(confirmButton).toBeDefined();
    expect(confirmButton!.attributes("type")).toBe("button");
    expect(
      wrapper.get('[data-testid="numeric-keypad-dialog"]').classes(),
    ).toEqual(
      expect.arrayContaining([
        "max-h-[92dvh]",
        "overflow-y-auto",
        "sm:top-1/2!",
        "sm:-translate-y-1/2!",
      ]),
    );
    await confirmButton!.trigger("click");

    expect(wrapper.emitted("update:modelValue")).toEqual([["1000.00"]]);
  });

  it("exposes focus without opening the calculator", async () => {
    const wrapper = mount(CockpitAmountPicker, {
      props: {
        modelValue: "500.00",
      },
      attachTo: document.body,
    });

    (wrapper.vm as unknown as { focus: () => void }).focus();
    await nextTick();

    expect(document.activeElement).toBe(
      wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]')
        .element,
    );
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

    wrapper.unmount();
  });

  it("carries the first focused numeric key into the calculator", async () => {
    const wrapper = mount(CockpitAmountPicker, {
      props: {
        modelValue: "500.00",
      },
      attachTo: document.body,
    });

    (wrapper.vm as unknown as { focus: () => void }).focus();
    await nextTick();

    wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .element.dispatchEvent(
        new KeyboardEvent("keydown", {
          key: "2",
          bubbles: true,
          cancelable: true,
        }),
      );
    await nextTick();

    expect(wrapper.get('[data-testid="numeric-keypad-display"]').text()).toBe(
      "₱2",
    );

    window.dispatchEvent(new KeyboardEvent("keydown", { key: "5" }));
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "." }));
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "7" }));
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "5" }));
    window.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter" }));
    await nextTick();

    expect(wrapper.emitted("update:modelValue")).toEqual([["25.75"]]);

    wrapper.unmount();
  });

  it("uses the Cockpit visual hierarchy without changing the generic default", async () => {
    const cockpit = mount(CockpitAmountPicker, {
      props: {
        modelValue: "1000.00",
      },
    });

    await cockpit
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .trigger("click");

    const cockpitDialog = cockpit.get('[data-testid="numeric-keypad-dialog"]');
    const cockpitConfirm = cockpit.get(
      '[data-testid="numeric-keypad-confirm"]',
    );
    const cockpitCancel = cockpit.get('[data-testid="numeric-keypad-cancel"]');

    expect(cockpitDialog.attributes("data-appearance")).toBe("cockpit");
    expect(cockpitDialog.classes()).toContain("border-emerald-200");
    expect(cockpitDialog.classes()).toContain("dark:bg-slate-950");
    expect(cockpitConfirm.classes()).toContain("bg-emerald-600");
    expect(cockpitConfirm.classes()).toContain("min-h-14");
    expect(cockpitConfirm.classes()).toContain("py-4");
    expect(cockpitCancel.classes()).toContain("min-h-14");
    expect(cockpitCancel.classes()).toContain("py-4");

    const generic = mount(NumericKeypad, {
      props: {
        open: true,
        mode: "amount",
        modelValue: 1000,
        allowDecimal: true,
      },
    });
    const genericDialog = generic.get('[data-testid="numeric-keypad-dialog"]');

    expect(genericDialog.attributes("data-appearance")).toBe("default");
    expect(genericDialog.classes()).not.toContain("border-emerald-200");

    cockpit.unmount();
    generic.unmount();
  });

  it("shows the live issue cost beneath the calculator amount", async () => {
    const wrapper = mount(CockpitAmountPicker, {
      props: {
        modelValue: "100.00",
        estimatedCost: "₱117.00",
        estimateAffordability: "insufficient-client-funds",
      },
    });

    await wrapper
      .get('[data-testid="cockpit-quick-generate-primary-amount"]')
      .trigger("click");

    const estimate = wrapper.get(
      '[data-testid="cockpit-amount-picker-estimated-cost"]',
    );

    expect(estimate.text()).toContain("Estimated Cost");
    expect(
      estimate
        .get('[data-testid="cockpit-amount-picker-estimated-cost-value"]')
        .text(),
    ).toBe("₱117.00");
    expect(estimate.attributes("data-affordability")).toBe(
      "insufficient-client-funds",
    );
    expect(estimate.get("span").classes()).toContain("text-rose-600");

    await wrapper.setProps({ estimatePending: true });

    expect(
      estimate
        .get('[data-testid="cockpit-amount-picker-estimated-cost-loading"]')
        .text(),
    ).toBe("Calculating…");

    await wrapper
      .get('[data-testid="numeric-keypad-quick-1000"]')
      .trigger("click");

    expect(wrapper.emitted("preview")?.at(-1)).toEqual([1000]);

    await wrapper.get('[data-testid="numeric-keypad-cancel"]').trigger("click");

    expect(wrapper.emitted("preview")?.at(-1)).toEqual([null]);
  });
});
