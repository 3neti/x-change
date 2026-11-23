import { describe, expect, it } from "vitest";
import {
  issuanceFailure,
  riderUrlError,
  urlEvidence,
} from "../../../resources/js/cockpit/issuanceErrors";

describe("issuance corrections", () => {
  it("preserves field paths and recognizable labels", () => {
    const result = issuanceFailure(422, {
      errors: {
        "rider.url": ["The link is invalid."],
        "feedback.mobile": ["Invalid mobile."],
        "cash.amount": ["Invalid amount."],
      },
    });
    expect(result.errors).toEqual([
      {
        path: "rider.url",
        field: "Rider → Link",
        message: "The link is invalid.",
      },
      {
        path: "feedback.mobile",
        field: "Status Updates → SMS",
        message: "Invalid mobile.",
      },
      { path: "cash.amount", field: "Amount", message: "Invalid amount." },
    ]);
  });
  it.each([401, 419, 403, 429, 500, 502, 0, 200])(
    "does not blame fields for non-validation status %s",
    (status) => {
      const failure = issuanceFailure(status, {
        message: "<html>secret stack trace</html>",
      });
      expect(failure.heading).not.toBe("Fix these fields before issuing");
      expect(JSON.stringify(failure)).not.toContain("secret");
    },
  );
  it("does not invent a field for an empty validation response", () => {
    expect(issuanceFailure(422, {}).errors[0].message).toContain(
      "did not identify a field",
    );
  });
  it("validates links without changing supported redirect policy", () => {
    expect(riderUrlError("")).toBeNull();
    expect(riderUrlError("not a URL")).toContain("URL");
    expect(
      riderUrlError("https://open.spotify.com/track/test?si=value"),
    ).toBeNull();
    expect(
      riderUrlError("https://other.test", {
        allowed_schemes: ["https"],
        allowed_hosts: ["allowed.test"],
        allow_any_host: false,
      }),
    ).toContain("permitted");
  });
  it("captures URL evidence without its contents", async () => {
    const evidence = await urlEvidence(
      "https://example.test/private?token=secret",
    );
    expect(evidence.present).toBe(true);
    expect(evidence.length).toBeGreaterThan(0);
    expect(JSON.stringify(evidence)).not.toContain("secret");
  });
});
