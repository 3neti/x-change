import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const packageRoot = process.cwd();

function source(path: string): string {
  return readFileSync(`${packageRoot}/${path}`, "utf8");
}

describe("public auto-generate discovery handoff", () => {
  it("applies server-validated public prefill only after resetting to the blank template", () => {
    const panel = source(
      "resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue",
    );

    expect(panel).toContain("if (props.publicMode)");
    expect(panel).toContain("startBlank();");
    expect(panel).toContain("if (props.publicPrefill)");
    expect(panel).toContain("amount.value = props.publicPrefill.amount;");
    expect(panel).toContain("currency.value = props.publicPrefill.currency;");
  });

  it("publishes canonical metadata and structured service data", () => {
    const page = source("resources/js/pages/x-change/public/AutoGenerate.vue");

    expect(page).toContain('rel="canonical"');
    expect(page).toContain('type="application/ld+json"');
    expect(page).toContain("props.service_discovery.description");
  });
});
