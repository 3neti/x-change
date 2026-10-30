import { flushPromises, mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import SettlementReview from "../../resources/js/pages/x-change/claim/SettlementReview.vue";
import SettlementWaiting from "../../resources/js/pages/x-change/claim/SettlementWaiting.vue";
import CockpitClaimLiveScreen from "../../resources/js/cockpit/components/CockpitClaimLiveScreen.vue";

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<span />" },
  useForm: (values: object) => ({
    ...values,
    errors: {},
    processing: false,
    post: vi.fn(),
  }),
}));

describe("settlement claim screens", () => {
  it("shows the patient the relevant waiting state", () => {
    const screen = mount(SettlementWaiting, {
      props: { code: "AA317", reviewed: false, paid: false },
    });
    expect(screen.text()).toContain("Waiting for the hospital");
    screen.setProps({ reviewed: true });
    return screen.vm
      .$nextTick()
      .then(() =>
        expect(screen.text()).toContain("Settlement is being prepared"),
      );
  });

  it("renders the review amount and disables submission in the walkthrough", () => {
    const screen = mount(SettlementReview, {
      props: {
        code: "AA317",
        amountMinor: 2000000,
        evidenceManifest: "",
        reviewed: false,
        paid: false,
        preview: true,
        evidence: [],
      },
    });
    expect(screen.text()).toContain("20,000.00");
    expect(screen.get("button").attributes("disabled")).toBeDefined();
  });

  it("loads the hospital review as an actual walkthrough screen", async () => {
    const screen = mount(CockpitClaimLiveScreen, {
      props: {
        screen: {
          kind: "settlement",
          title: "Hospital review",
          component: "x-change/claim/SettlementReview",
          props: {
            code: "AA317",
            amountMinor: 2000000,
            evidenceManifest: "",
            reviewed: false,
            paid: false,
            preview: true,
            evidence: [],
          },
        },
      },
    });
    await flushPromises();
    expect(screen.text()).toContain("Hospital review");
    expect(screen.text()).toContain("20,000.00");
    expect(screen.get("button").attributes("disabled")).toBeDefined();
  });
});
