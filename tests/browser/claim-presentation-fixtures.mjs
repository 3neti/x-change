export const presentation = {
  schema: "form-flow.presentation.v1",
  profile: "guided-claim-v1",
  brand: {
    name: "Pay Code",
    logo: "/vendor/x-change/images/pay-code/pay-code-logo.svg",
    placement: "top_left",
    logo_height: 32,
    logo_max_width: 112,
  },
  layout: {
    artwork_aspect_ratio: "1200/630",
    density: "compact",
    capture_surface: "fill_available",
    scrolling: "when_needed",
  },
  actions: {
    placement: "viewport_bottom",
    primary_position: "right",
    secondary_position: "left",
    reserve_content_space: true,
    primary_label: null,
    secondary_label: null,
  },
  progress: { show: true, current: 1, total: 2 },
  diagnostics: { show_versions: false },
};
export async function render(page, component, props) {
  const base = process.env.WORKFLOW_BROWSER_URL;
  if (!base || !new URL(base).hostname.endsWith(".test"))
    throw Error("Local host required");
  await page.route("**/*", async (route) => {
    const request = route.request();
    if (new URL(request.url()).origin !== base || request.method() !== "GET")
      return route.abort();
    if (new URL(request.url()).pathname !== "/login") return route.continue();
    const response = await route.fetch(),
      html = await response.text();
    const pattern = /(<script[^>]*data-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;
    const match = html.match(pattern);
    if (!match) throw Error("Inertia page script missing");
    const original = JSON.parse(match[2]);
    const fixture = {
      ...original,
      component,
      props: {
        ...original.props,
        flow_id: "synthetic-layout",
        step_index: 0,
        preview_mode: true,
        presentation,
        ui_variant: "compact",
        action_placement: "viewport_bottom",
        ...props,
      },
    };
    await route.fulfill({
      response,
      body: html.replace(
        pattern,
        (_, a, ignored, z) =>
          a + JSON.stringify(fixture).replaceAll("<", "\\u003c") + z,
      ),
    });
  });
  await page.goto(`${base}/login`);
}
