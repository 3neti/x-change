import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Analytics from "../../../resources/js/pages/x-change/public/Analytics.vue";

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div><slot /></div>" },
  Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
}));

describe("Public analytics", () => {
  it("presents aggregate activity with period navigation and charts", () => {
    const wrapper = mount(Analytics, {
      props: {
        public_navigation: {
          pricing_url: "/x/pricing",
          analytics_url: "/x/analytics",
          claim_url: "/x/claim",
          create_url: "/x/auto-generate",
          login_url: "/login",
          register_url: "/register",
        },
        analytics: {
          period: "30d",
          available_periods: [
            { key: "today", label: "Today" },
            { key: "30d", label: "Last 30 days" },
          ],
          totals: {
            batches: 3,
            recipients: 8,
            issued: 8,
            issued_value_minor: 12500,
            claimed: 5,
            claimed_value_minor: 7500,
            claim_rate: 62.5,
          },
          activity: [{ date: "2026-10-14", issued: 8, claimed: 5 }],
          lifecycle: [{ key: "issued", label: "Issued", value: 8 }],
          funding_methods: [{ key: "qr_ph", label: "Qr Ph", value: 3 }],
          batch_sizes: [{ label: "1–2", value: 3 }],
          batch_completion: [{ label: "Under 1 min", value: 2 }],
        },
      },
    });

    expect(wrapper.get('[data-testid="public-analytics-page"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="analytics-kpis"]').text()).toContain("62.5%");
    expect(wrapper.get('[data-testid="analytics-activity-chart"]').exists()).toBe(true);
    expect(wrapper.get('[data-testid="analytics-funding-chart"]').text()).toContain("Qr Ph");
    expect(wrapper.get('[data-testid="analytics-periods"] a').attributes("href")).toContain("period=today");
  });
});
