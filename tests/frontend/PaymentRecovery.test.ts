import { mount } from "@vue/test-utils";
import { reactive } from "vue";
import { expect, it, vi } from "vitest";
import PaymentRecovery from "../../resources/js/pages/x-change/operations/PaymentRecovery.vue";

const post = vi.fn();
vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<span />" },
  useForm: () =>
    reactive({
      reason: "",
      destination_ready: false,
      processing: false,
      errors: {},
      post,
      reset() {
        this.reason = "";
        this.destination_ready = false;
      },
      clearErrors() {},
    }),
}));

it("shows treasury payouts held for review without offering a transfer retry", () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [], fundingCases: [], reports: [], failures: [], payouts: [],
      treasuryPayoutReviews: [{ id: 42, code: "ABCD", created_at: "2026-11-07", updated_at: "2026-11-07" }],
    },
  });
  expect(wrapper.text()).toContain("ABCD · Review 42");
  expect(wrapper.text()).toContain("Check provider and reconciliation evidence");
  expect(wrapper.get("button").text()).toBe("Record investigation");
  expect(wrapper.text()).not.toContain("Retry transfer");
});

it("collects a reason and provider evidence before requesting a reservation release", async () => {
  const wrapper = mount(PaymentRecovery, { props: {
    transactionReviews: [], fundingCases: [], reports: [], failures: [], payouts: [],
    partnerReservations: [{ id: 9, external_reference: 'INV-1', order_reference: 'ORDER-1', order_status: 'expired', expires_at: '2026-11-01', last_checked_at: '2026-11-09', last_outcome: 'not_observed', release_url: '/x/operations/recovery/partner-reservations/9/release', verify_url: '/x/operations/recovery/partner-reservations/9/verify' }],
  } });
  expect(wrapper.text()).toContain('INV-1');
  await wrapper.findAll('button').find((button) => button.text() === 'Review release')!.trigger('click');
  await wrapper.get('input').setValue('provider-case-123');
  await wrapper.get('textarea').setValue('Provider confirmed no payment');
  await wrapper.get('form').trigger('submit');
  expect(post).toHaveBeenCalledWith('/x/operations/recovery/partner-reservations/9/release', expect.objectContaining({ preserveScroll: true }));
});

it("records an investigation when provider transaction evidence is missing", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [], fundingCases: [], reports: [], failures: [], payouts: [],
      treasuryPayoutReviews: [{ id: 42, code: "ABCD", created_at: "2026-11-07", updated_at: "2026-11-07", can_check: false }],
    },
  });
  await wrapper.get("button").trigger("click");
  await wrapper.get("textarea").setValue("Provider investigation opened");
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/x/operations/recovery/treasury-payouts/42/investigate");
});

it("checks a treasury payout with provider evidence and a reason", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [], fundingCases: [], reports: [], failures: [], payouts: [],
      treasuryPayoutReviews: [{ id: 42, code: "ABCD", created_at: "2026-11-07", updated_at: "2026-11-07", can_check: true }],
    },
  });
  await wrapper.get("button").trigger("click");
  await wrapper.get("textarea").setValue("Provider status checked");
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/x/operations/recovery/treasury-payouts/42/review");
});

it("shows masked recovery cases and requires an explicit confirmation for a retry", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [
        {
          id: 1,
          code: "PMKA",
          reason_code: "payment_attribution_or_eligibility",
          amount_minor: 2000,
          currency: "PHP",
        },
      ],
      fundingCases: [],
      reports: [],
      failures: [],
      payouts: [
        {
          id: 1,
          code: "PMKA",
          amount: "20.00",
          currency: "PHP",
          destination: "•••• 9820",
          status: "failed",
          last_checked_at: null,
          retry_url: "/retry/1",
        },
      ],
    },
  });
  expect(wrapper.text()).toContain("PMKA");
  expect(wrapper.text()).toContain("•••• 9820");
  expect(wrapper.find("form").exists()).toBe(false);
  await wrapper.get("button").trigger("click");
  expect(
    wrapper.get('input[type="checkbox"]').attributes("required"),
  ).toBeDefined();
  await wrapper.get('input[type="checkbox"]').setValue(true);
  await wrapper
    .get("input:not([type])")
    .setValue("Recipient confirmed account available");
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/retry/1", expect.any(Object));
});

it("requires a reason and confirmation before recording that a payment was accounted elsewhere", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [
        {
          id: 2,
          code: "PMKA",
          reason_code: "review",
          resolution_url: "/accounted/2",
        },
      ],
      fundingCases: [],
      reports: [],
      failures: [],
      payouts: [],
    },
  });
  await wrapper.get("button").trigger("click");
  expect(wrapper.text()).toContain("This action does not transfer money");
  expect(
    wrapper.get('input[type="checkbox"]').attributes("required"),
  ).toBeDefined();
  expect(wrapper.get("textarea").attributes("required")).toBeDefined();
  await wrapper.get('input[type="checkbox"]').setValue(true);
  await wrapper.get("textarea").setValue("Matched to an existing collection");
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/accounted/2", expect.any(Object));
});

it("offers the authorized excess and attempt decisions with explicit confirmation", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [
        {
          id: 3,
          code: "PMKA",
          reason_code: "review",
          resolution_url: "/accounted/3",
          credit_url: "/credit/3",
          apply_url: "/apply/3",
          candidates: [
            {
              id: 12,
              code: "PMKA",
              reference: "attempt-12",
              expected_amount_minor: 2000,
              currency: "PHP",
            },
          ],
        },
      ],
      fundingCases: [],
      reports: [],
      failures: [],
      payouts: [],
    },
  });
  await wrapper.get("button").trigger("click");
  const button = (label: string) =>
    wrapper
      .findAll("button")
      .find((element) => element.text().includes(label))!;
  await button("Apply to a payment attempt").trigger("click");
  expect(wrapper.text()).toContain("attempt-12");
  expect(wrapper.get("select").attributes("required")).toBeDefined();
  expect(
    wrapper.get('input[type="checkbox"]').attributes("required"),
  ).toBeDefined();
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/apply/3", expect.any(Object));
  await button("Credit excess to Client Funds").trigger("click");
  expect(wrapper.find("select").exists()).toBe(false);
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/credit/3", expect.any(Object));
});

it("shows the exact reviewed split before allowing a decision", async () => {
  const wrapper = mount(PaymentRecovery, {
    props: {
      transactionReviews: [
        {
          id: 4,
          code: "SPLIT",
          resolution_url: "/accounted/4",
          split_url: "/split/4",
          candidates: [
            {
              id: 13,
              code: "SPLIT",
              reference: "attempt-13",
              expected_amount_minor: 10000,
              currency: "PHP",
              split_collection_minor: 10000,
              split_excess_minor: 2000,
            },
          ],
        },
      ],
      fundingCases: [],
      reports: [],
      failures: [],
      payouts: [],
    },
  });
  await wrapper.get("button").trigger("click");
  await wrapper
    .findAll("button")
    .find((button) => button.text().includes("Collect and credit excess"))!
    .trigger("click");
  expect(
    wrapper.get('button[type="submit"]').attributes("disabled"),
  ).toBeDefined();
  await wrapper.get("select").setValue("13");
  expect(wrapper.text()).toContain("Collect PHP 100.00");
  expect(wrapper.text()).toContain("Credit Client Funds PHP 20.00");
  await wrapper.get("form").trigger("submit");
  expect(post).toHaveBeenCalledWith("/split/4", expect.any(Object));
});
