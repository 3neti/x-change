import { enableAutoUnmount, flushPromises, mount } from "@vue/test-utils";
import { afterEach, describe, expect, it, vi } from "vitest";
import CockpitQuickGenerateSubmitPanel from "../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue";
import { cockpitQuickGenerateTemplates } from "../../../resources/js/cockpit/quickGenerateDefaults";
vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: {} }),
  Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
  router: { patch: vi.fn(), post: vi.fn(), reload: vi.fn() },
}));
enableAutoUnmount(afterEach);
afterEach(() => vi.unstubAllGlobals());

function mountPanel(
  response: Response | Error,
  publicMode = false,
  extraProps: Record<string, unknown> = {},
) {
  const fetchMock = vi.fn(async (url: string, _options?: RequestInit) => {
    if (url === "/x/cockpit/quick-generate/diagnostics")
      return Response.json({ recorded: true }, { status: 202 });
    if (url === "/issue") {
      if (response instanceof Error) throw response;
      return response;
    }
    return Response.json({});
  });
  vi.stubGlobal("fetch", fetchMock);
  const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
    attachTo: document.body,
    props: {
      templates: cockpitQuickGenerateTemplates,
      ...extraProps,
      publicMode,
      mutationContract: {
        runtime_enabled: true,
        route: "issue",
        route_url: "/issue",
        allowed_methods: ["POST"],
      },
    },
    global: { stubs: { Teleport: true, CockpitPayCodeCanvas: true } },
  });
  return { wrapper, fetchMock };
}

describe("issuance failure experience", () => {
  it("opens Rider Link from a named server correction and focuses the input", async () => {
    const { wrapper } = mountPanel(
      Response.json(
        { errors: { "rider.url": ["Choose a valid destination."] } },
        { status: 422 },
      ),
    );
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    expect(
      wrapper
        .get('[data-testid="cockpit-quick-generate-submission-errors"]')
        .text(),
    ).toContain("Rider → Link");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submission-error"] button')
      .trigger("click");
    await flushPromises();
    expect(wrapper.get('[data-testid="rider-editor-url-error"]').text()).toBe(
      "Choose a valid destination.",
    );
    expect(document.activeElement).toBe(
      wrapper.get('[data-testid="rider-editor-url"]').element,
    );
    await wrapper
      .get('[data-testid="rider-editor-url"]')
      .setValue("https://example.test/valid");
    expect(
      wrapper.find('[data-testid="rider-editor-url-error"]').exists(),
    ).toBe(false);
  });
  it.each([401, 419, 403, 429, 500, 502])(
    "preserves the draft and reference for HTML status %s",
    async (status) => {
      const { wrapper, fetchMock } = mountPanel(
        new Response("<html>PRIVATE TOKEN</html>", {
          status,
          headers: { "content-type": "text/html", "cf-ray": "edge-123" },
        }),
      );
      await wrapper
        .get('[data-testid="cockpit-quick-generate-primary-purpose"]')
        .setValue("Parking");
      await wrapper
        .get('[data-testid="cockpit-quick-generate-submit-panel"]')
        .trigger("submit");
      await flushPromises();
      const panel = wrapper.get(
        '[data-testid="cockpit-quick-generate-submission-errors"]',
      );
      expect(panel.text()).not.toContain("Fix these fields");
      expect(panel.text()).not.toContain("PRIVATE TOKEN");
      expect(panel.text()).toContain("Reference:");
      expect(panel.text()).toContain("Diagnostic report saved.");
      expect(
        wrapper.get<HTMLInputElement>(
          '[data-testid="cockpit-quick-generate-primary-purpose"]',
        ).element.value,
      ).toBe("Parking");
      const report = fetchMock.mock.calls.find(([url]) =>
        url.endsWith("/diagnostics"),
      )!;
      expect(JSON.stringify(report)).not.toContain("PRIVATE TOKEN");
      expect(
        fetchMock.mock.calls.filter(([url]) => url === "/issue"),
      ).toHaveLength(1);
    },
  );
  it("keeps a reference when network failure also prevents reporting", async () => {
    const { wrapper, fetchMock } = mountPanel(
      new Error("private URL must not appear"),
    );
    fetchMock.mockRejectedValue(new Error("offline"));
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    const panel = wrapper.get(
      '[data-testid="cockpit-quick-generate-submission-errors"]',
    );
    expect(panel.text()).toContain("Connection issue");
    expect(panel.text()).toContain("could not be saved");
    expect(panel.text()).toContain("Reference:");
    expect(panel.text()).not.toContain("private URL");
  });
  it("does not send anonymous Auto Generate diagnostics to the cockpit endpoint", async () => {
    const { wrapper, fetchMock } = mountPanel(
      new Response("", { status: 403 }),
      true,
    );
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    expect(
      fetchMock.mock.calls.some(([url]) => url.endsWith("/diagnostics")),
    ).toBe(false);
  });
  it("blocks an invalid Rider URL locally and opens the Link correction without issuing", async () => {
    const { wrapper, fetchMock } = mountPanel(Response.json({}));
    await wrapper.get('[data-testid="open-rider-editor"]').trigger("click");
    const link = wrapper
      .get('[data-testid="rider-editor"]')
      .findAll('[role="tab"]')
      .find((tab) => tab.text() === "Link")!;
    await link.trigger("mousedown", { button: 0 });
    await wrapper
      .get('[data-testid="rider-editor-url"]')
      .setValue("invalid link");
    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Done")!
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    expect(
      wrapper.get('[data-testid="rider-editor-url-error"]').text(),
    ).toContain("valid URL");
    expect(
      fetchMock.mock.calls.filter(([url]) => url === "/issue"),
    ).toHaveLength(0);
    const report = JSON.parse(
      String(
        fetchMock.mock.calls.find(([url]) => url.endsWith("/diagnostics"))![1]
          ?.body,
      ),
    );
    expect(report.failure_kind).toBe("client_validation");
    expect(report.error_fields).toEqual(["rider.url"]);
    expect(JSON.stringify(report)).not.toContain("invalid link");
  });

  it("keeps an uncertain successful HTTP response safe and never retries issuance automatically", async () => {
    const { wrapper, fetchMock } = mountPanel(
      new Response("<html>private exception</html>", {
        status: 200,
        headers: { "content-type": "text/html" },
      }),
    );
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    expect(
      wrapper
        .get('[data-testid="cockpit-quick-generate-submission-errors"]')
        .text(),
    ).toContain("Check Pay Codes");
    expect(wrapper.text()).not.toContain("private exception");
    expect(
      fetchMock.mock.calls.filter(([url]) => url === "/issue"),
    ).toHaveLength(1);
  });

  it("reports sanitized response metadata with a separate reference for each deliberate submission", async () => {
    const { wrapper, fetchMock } = mountPanel(
      new Response("", {
        status: 403,
        headers: {
          "content-type": "text/html; charset=utf-8",
          "x-request-id": "request-42",
        },
      }),
    );
    // A fresh response is necessary because each response body is read once.
    fetchMock.mockImplementation(async (url: string) =>
      url === "/issue"
        ? new Response("", {
            status: 403,
            headers: {
              "content-type": "text/html; charset=utf-8",
              "x-request-id": "request-42",
            },
          })
        : Response.json({ recorded: true }, { status: 202 }),
    );
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    const issues = fetchMock.mock.calls.filter(([url]) => url === "/issue");
    expect(issues).toHaveLength(2);
    const first = issues[0][1]?.headers as Record<string, string>;
    const second = issues[1][1]?.headers as Record<string, string>;
    expect(first["Idempotency-Key"]).toBeTruthy();
    expect(first["X-XChange-Submission-Reference"]).not.toBe(
      first["Idempotency-Key"],
    );
    expect(second["X-XChange-Submission-Reference"]).not.toBe(
      second["Idempotency-Key"],
    );
    expect(second["X-XChange-Submission-Reference"]).not.toBe(
      first["X-XChange-Submission-Reference"],
    );
    const reports = fetchMock.mock.calls.filter(([url]) =>
      url.endsWith("/diagnostics"),
    );
    expect(reports).toHaveLength(2);
    expect(JSON.parse(String(reports[0][1]?.body))).toMatchObject({
      reference: first["X-XChange-Submission-Reference"],
      http_status: 403,
      content_type: "text/html",
      request_id: "request-42",
      failure_kind: "http",
    });
  });

  it("shows a server feedback correction next to the current control and focuses it", async () => {
    const { wrapper } = mountPanel(
      Response.json(
        { errors: { "feedback.email": ["Choose a valid email."] } },
        { status: 422 },
      ),
    );
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submission-error"] button')
      .trigger("click");
    await flushPromises();
    expect(
      wrapper.get('[data-testid="feedback-server-error"]').text(),
    ).toContain("Status Updates → Email: Choose a valid email.");
    expect(document.activeElement).toBe(
      wrapper.get(
        '[data-testid="cockpit-quick-generate-primary-feedback"] input',
      ).element,
    );
  });
  it("validates a template-loaded invalid link before any issuance request", async () => {
    const { wrapper, fetchMock } = mountPanel(Response.json({}), false, {
      savedTemplates: [
        {
          reference: "01TEMPLATE",
          name: "Double A",
          base_template_key: "blank-pay-code",
          include_amount: true,
          include_purpose: true,
          instructions: {
            cash: { amount: 100, currency: "PHP" },
            rider: { message: "Parking", url: "not-a-valid-url" },
          },
        },
      ],
    });
    await wrapper.get('[data-testid="open-templates-editor"]').trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-choose-template"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-saved-template-option"]')
      .trigger("click");
    await wrapper
      .get('[data-testid="cockpit-quick-generate-submit-panel"]')
      .trigger("submit");
    await flushPromises();
    expect(
      wrapper.get('[data-testid="rider-editor-url-error"]').text(),
    ).toContain("valid URL");
    expect(fetchMock.mock.calls.some(([url]) => url === "/issue")).toBe(false);
  });
});
