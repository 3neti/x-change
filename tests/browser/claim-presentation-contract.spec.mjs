import { createRequire } from "node:module";
import { presentation, render } from "./claim-presentation-fixtures.mjs";
const require = createRequire(
  `${process.env.WORKFLOW_BROWSER_HOST}/package.json`,
);
const { test, expect } = require("@playwright/test");
const voucher = {
  code: "ABCD1234EF56",
  amount: 100,
  currency: "PHP",
  formatted_amount: "₱100.00",
};
const preview = (kind) => ({
  component: "x-change/claim/Preview",
  props: {
    viewport: { profile: "mobile", width: 402, height: 874 },
    step: {
      title: kind,
      screen: {
        kind,
        code: "AA317",
        amount: "₱100.00",
        title: kind,
        fields: [],
      },
    },
  },
});
const rows = [
  ["entry", preview("claim_entry")],
  [
    "splash",
    {
      component: "form-flow/core/Splash",
      props: {
        content: "",
        is_default_splash: true,
        voucher_code: voucher.code,
        timeout: 0,
      },
    },
  ],
  [
    "details",
    {
      component: "form-flow/core/GenericForm",
      props: {
        title: "Disbursement Details",
        fields: [
          {
            name: "mobile",
            type: "tel",
            label: "Mobile number",
            group: "redeemer",
          },
          {
            name: "account_number",
            type: "text",
            label: "Account number",
            group: "endorsement",
          },
        ],
      },
    },
  ],
  [
    "personal_information",
    {
      component: "form-flow/core/GenericForm",
      props: {
        title: "Personal information",
        fields: [
          { name: "full_name", type: "text", label: "Full name" },
          { name: "email", type: "email", label: "Email" },
        ],
      },
    },
  ],
  [
    "selfie",
    {
      component: "form-flow/selfie/SelfieCapturePage",
      props: { step: "0", config: { width: 640, height: 480, quality: 0.9 } },
    },
  ],
  [
    "signature",
    {
      component: "form-flow/signature/SignatureCapturePage",
      props: { step: "0", config: { height: 256 } },
    },
  ],
  [
    "location",
    {
      component: "form-flow/location/LocationCapturePage",
      props: { step: "0", config: {} },
    },
  ],
  [
    "otp",
    {
      component: "form-flow/otp/OtpCapturePage",
      props: {
        step: "0",
        mobile: "09170000000",
        challenge_status: "idle",
        config: { max_resends: 3, resend_cooldown: 30, digits: 6 },
      },
    },
  ],
  [
    "verify",
    {
      component: "form-flow/otp/OtpCapturePage",
      props: {
        step: "0",
        mobile: "09170000000",
        challenge_status: "sent",
        config: { max_resends: 3, resend_cooldown: 30, digits: 6 },
      },
    },
  ],
  [
    "kyc",
    {
      component: "form-flow/kyc/KYCInitiatePage",
      props: { step: "0", config: {}, kyc_status: null },
    },
  ],
  [
    "kyc_pending",
    {
      component: "form-flow/kyc/KYCStatusPage",
      props: {
        transaction_id: "synthetic",
        initial_status: "needs_review",
        status_url: "/synthetic",
        retry_url: "/synthetic",
        continue_url: "/synthetic",
      },
    },
  ],
  [
    "kyc_retry",
    {
      component: "form-flow/kyc/KYCStatusPage",
      props: {
        transaction_id: "synthetic",
        initial_status: "rejected",
        can_retry: true,
        status_url: "/synthetic",
        retry_url: "/synthetic",
        continue_url: "/synthetic",
      },
    },
  ],
  [
    "confirmation",
    {
      component: "x-change/claim/Confirm",
      props: {
        claim: {
          voucher_code: voucher.code,
          amount: 100,
          currency: "PHP",
          formatted_amount: "₱100.00",
          reference_id: "synthetic",
          flow_id: "synthetic",
          collected_summary: { mobile: "09170000000" },
        },
      },
    },
  ],
  ["success", { component: "x-change/claim/Success", props: { voucher } }],
  [
    "pending",
    {
      component: "x-change/claim/Approval",
      props: {
        voucher,
        approval: {
          required: true,
          provider: "synthetic",
          authorization_type: "manual",
          reference_id: "synthetic",
          otp_required: false,
          message: "Waiting for approval",
        },
      },
    },
  ],
  [
    "auth_required",
    {
      component: "x-change/claim/AuthRequired",
      props: {
        code: voucher.code,
        login_url: "/login",
        claim_url: "/x/claim",
        intent: {
          type: "campaign_authorization",
          authentication_mode: "authenticated_officer",
          code: voucher.code,
          title: "Sign in to authorize",
          description: "Use your authorized account.",
          intended_url: "/x/claim",
        },
        workflow: {
          key: "synthetic",
          title: "Authorization",
          description: "Review the request.",
        },
      },
    },
  ],
  [
    "error",
    {
      component: "x-change/claim/Error",
      props: { code: voucher.code, message: "Synthetic unavailable state" },
    },
  ],
  [
    "payment",
    {
      component: "x-change/claim/Payment",
      props: {
        payment: {
          pay_code: voucher.code,
          currency: "PHP",
          target_amount_minor: 10000,
          collected_amount_minor: 0,
          amount_due_minor: 10000,
          is_fully_paid: false,
          provider: "netbank",
          provider_available: false,
          can_create_attempt: false,
          attempt: null,
          receipt: null,
        },
      },
    },
  ],
  [
    "payment_handoff",
    {
      component: "x-change/claim/PaymentHandoff",
      props: {
        code: voucher.code,
        payment_url: "/x/pay/synthetic",
        is_fully_collected: false,
        claim_url: "/x/claim",
      },
    },
  ],
  [
    "completion",
    {
      component: "form-flow/core/Complete",
      props: {
        state: {
          reference_id: "synthetic",
          collected_data: [],
          completed_at: "2026-10-19T00:00:00Z",
        },
        callback_triggered: false,
      },
    },
  ],
  [
    "missing_handler",
    {
      component: "form-flow/core/MissingHandler",
      props: {
        handler_name: "synthetic",
        handler_title: "Synthetic evidence",
        install_hint: "composer require example/handler",
        is_production: true,
        can_skip: false,
      },
    },
  ],
  ["rider_url", preview("rider_handoff")],
];
const contrast = {
  ...presentation,
  appearance: {
    mode: "light",
    light: {
      canvas: "#f0f9ff",
      surface: "#ffffff",
      foreground: "#102030",
      muted: "#475569",
      border: "#64748b",
      accent: "#123456",
      accent_foreground: "#ffffff",
    },
    dark: {
      canvas: "#112233",
      surface: "#223344",
      foreground: "#ffffff",
      muted: "#cbd5e1",
      border: "#64748b",
      accent: "#ffffff",
      accent_foreground: "#112233",
    },
  },
  tokens: {
    content_width: "wide",
    spacing: "spacious",
    typography: "large",
    control_size: "large",
  },
  brand: { ...presentation.brand, placement: "center", logo_height: 40 },
  actions: {
    ...presentation.actions,
    placement: "inline",
    roles: {
      capture: { label: "Take photo" },
      verify: { label: "Verify code" },
      retry: { label: "Try again" },
    },
  },
};
for (const width of [360, 402, 1280])
  for (const [name, row] of rows)
    for (const variant of ["default", "contrast"]) {
      test(`${name} ${variant} profile at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 874 });
        const errors = [];
        page.on("pageerror", (e) => errors.push(e.message));
        const profile = variant === "contrast" ? contrast : presentation;
        await render(page, row.component, {
          ...row.props,
          presentation: profile,
          claim_ui: profile,
        });
        const root = page.getByTestId("form-flow-presentation").first();
        await expect(root).toBeVisible();
        if (variant === "contrast") {
          await expect(root).toHaveCSS("--flow-content-width", "42rem");
          const control = root
            .locator(
              "button,input:not([type=hidden]):not([type=checkbox]):not([type=radio])",
            )
            .first();
          if (await control.count())
            expect((await control.boundingBox()).height).toBeGreaterThanOrEqual(
              52,
            );
          const title = root.locator("h1,h2,h3,[data-slot=card-title]").first();
          if (await title.count())
            await expect(title).toHaveCSS("font-size", "28px");
        }
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth + 1,
          ),
        ).toBe(true);
        expect(errors).toEqual([]);
      });
    }
for (const placement of [
  "inline",
  "bottom",
  "bottom_sticky",
  "viewport_bottom",
])
  test(`action placement ${placement}`, async ({ page }) => {
    await page.setViewportSize({ width: 402, height: 874 });
    await render(page, "form-flow/core/Splash", {
      content: "",
      is_default_splash: true,
      voucher_code: "AA317",
      timeout: 0,
      presentation: {
        ...contrast,
        actions: { ...contrast.actions, placement },
      },
    });
    await expect(page.locator("[data-flow-actions]").first()).toHaveCSS(
      "position",
      placement === "viewport_bottom"
        ? "fixed"
        : placement === "bottom_sticky"
          ? "sticky"
          : "static",
    );
  });
for (const capture_surface of ["contained", "fill_available"])
  test(`signature capture ${capture_surface} stays request free`, async ({
    page,
  }) => {
    let posts = 0;
    page.on("request", (r) => {
      if (r.method() !== "GET") posts++;
    });
    await render(page, "form-flow/signature/SignatureCapturePage", {
      step: "0",
      config: { height: 256 },
      presentation: {
        ...contrast,
        layout: { ...contrast.layout, capture_surface },
      },
    });
    await expect(page.locator("[data-flow-capture-surface]")).toBeVisible();
    await expect(
      page.getByRole("button", { name: "Continue", exact: true }),
    ).toBeDisabled();
    expect(posts).toBe(0);
  });

test("named screen overrides affect width action copy and diagnostic visibility", async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 874 });
  await render(page, "form-flow/core/GenericForm", {
    title: "Named screen",
    fields: [{ name: "name", type: "text", label: "Name" }],
    package_versions: [{ name: "3neti/form-flow", version: "synthetic" }],
    show_package_versions: true,
    presentation: {
      ...contrast,
      screens: {
        form: {
          tokens: { content_width: "narrow", control_size: "standard" },
          actions: { roles: { continue: { label: "Next details" } } },
          diagnostics: { show_versions: false },
        },
      },
    },
  });
  const content = page.locator("[data-flow-content]").first();
  await expect(content).toHaveCSS("max-width", "384px");
  await expect(
    page.getByRole("button", { name: "Next details", exact: true }),
  ).toBeVisible();
  await expect(page.getByText("QA build", { exact: true })).toHaveCount(0);
  await expect(
    page.getByRole("textbox", { name: "Name", exact: true }),
  ).toHaveCSS("min-height", "44px");
});
test("large text retains readable content and reachable actions", async ({
  page,
}) => {
  await page.setViewportSize({ width: 360, height: 874 });
  await render(page, "x-change/claim/Confirm", {
    claim_ui: contrast,
    claim: {
      voucher_code: "ABCD1234EF56",
      amount: 100,
      currency: "PHP",
      formatted_amount: "₱100.00",
      reference_id: "synthetic",
      flow_id: "synthetic",
      collected_summary: { mobile: "09170000000" },
    },
  });
  await page.addStyleTag({ content: "html {font-size:32px !important}" });
  await expect(
    page.getByRole("heading", { name: "Confirm Claim", exact: true }),
  ).toBeVisible();
  await page
    .getByRole("button", { name: "Confirm & Claim", exact: true })
    .scrollIntoViewIfNeeded();
  await page.screenshot({path:test.info().outputPath('large-text.png'),fullPage:true});
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth + 1,
    ),
  ).toBe(true);
});
for (const [name, component, props, label] of [
  [
    "camera",
    "form-flow/selfie/SelfieCapturePage",
    { step: "0", config: {} },
    "Take photo",
  ],
  [
    "OTP send",
    "form-flow/otp/OtpCapturePage",
    {
      step: "0",
      mobile: "09170000000",
      challenge_status: "idle",
      config: { max_resends: 3, resend_cooldown: 30, digits: 6 },
    },
    "Send Verification Code",
  ],
  [
    "KYC retry",
    "form-flow/kyc/KYCStatusPage",
    {
      transaction_id: "synthetic",
      initial_status: "rejected",
      can_retry: true,
      status_url: "/synthetic",
      retry_url: "/synthetic",
      continue_url: "/synthetic",
    },
    "Try again",
  ],
])
  test(`${name} preview activation stays inert`, async ({ page }) => {
    let writes = 0;
    await page.addInitScript(() => {
      window.__captureRequests = 0;
      Object.defineProperty(navigator, "mediaDevices", {
        configurable: true,
        value: {
          getUserMedia: () => {
            window.__captureRequests++;
            return Promise.reject(Error("Unexpected camera request"));
          },
        },
      });
    });
    page.on("request", (r) => {
      if (r.method() !== "GET") writes++;
    });
    await render(page, component, { ...props, presentation: contrast });
    await page.getByRole("button", { name: label, exact: true }).click();
    expect(writes).toBe(0);
    expect(await page.evaluate(() => window.__captureRequests)).toBe(0);
  });
