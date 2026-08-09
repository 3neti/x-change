import { describe, expect, it } from "vitest";
import { classifyCockpitPayee } from "../../../resources/js/cockpit/payeePolicy";

describe("Cockpit Pay To policy", () => {
  it.each([
    ["", "open", null, true],
    ["CASH", "open", null, true],
    ["09173011987", "mobile", "+639173011987", true],
    ["+63 917 301 1987", "mobile", "+639173011987", true],
    ["person@example.com", "email", "person@example.com", false],
    ["@merchant_1", "vendor", "MERCHANT_1", true],
    ["release-me", "secret", "release-me", true],
    ['"09173011987"', "secret", "09173011987", true],
    ['"CASH"', "secret", "CASH", true],
  ])("classifies %s as %s", (input, kind, normalizedValue, issuable) => {
    const result = classifyCockpitPayee(input);

    expect(result.kind).toBe(kind);
    expect(result.normalizedValue).toBe(normalizedValue);
    expect(result.issuable).toBe(issuable);
  });

  it.each([
    ["0917301198", "complete Philippine mobile"],
    ['"09173011987', "Close both double quotes"],
    ["a@invalid", "complete email address"],
    ["@bad alias", "Vendor aliases"],
    ["123", "4 to 255"],
  ])("fails closed for %s", (input, message) => {
    const result = classifyCockpitPayee(input);

    expect(result.kind).toBe("invalid");
    expect(result.issuable).toBe(false);
    expect(result.message).toContain(message);
  });
});
