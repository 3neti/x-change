import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import { nextTick } from "vue";
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
});
