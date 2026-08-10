import { describe, expect, it } from "vitest";
import {
  destinationInstitution,
  iconAssetForCode,
  iconAssetForProvider,
  iconAssetForRail,
  orchestratorIconAsset,
  payoutRouteIcons,
  payoutRouteSegments,
  payoutRouteSentence,
  settlementRailLabel,
} from "../../resources/js/components/x-change/support/payoutDestinations";

describe("payout destination display helpers", () => {
  it("labels GCash and formats the visible route", () => {
    expect(destinationInstitution("GXCHPHM2XXX")).toMatchObject({
      label: "GCash",
      shortLabel: "GCash",
      category: "wallet",
    });

    expect(
      payoutRouteSegments({
        bankCode: "GXCHPHM2XXX",
        settlementRail: "INSTAPAY",
        accountNumber: "09173011987",
      }),
    ).toEqual(["x-change", "NetBank", "InstaPay", "GCash", "09173011987"]);
  });

  it("keeps Maya Wallet and Maya Bank distinct", () => {
    expect(destinationInstitution("PAPHPHM1XXX")).toMatchObject({
      shortLabel: "Maya Wallet",
      category: "wallet",
    });
    expect(destinationInstitution("MYDBPHM2XXX")).toMatchObject({
      shortLabel: "Maya Bank",
      category: "bank",
    });
  });

  it("resolves an unconfigured bank code via the canonical bank directory", () => {
    expect(destinationInstitution("PNBMPHMMTOD")).toMatchObject({
      label: "Philippine National Bank",
      shortLabel: "Philippine National Bank",
      category: "bank",
      iconAsset: "/vendor/x-change/images/payout-destinations/pnb-128.png",
    });
  });

  it("falls back to the raw code for a genuinely unknown institution", () => {
    expect(destinationInstitution("NOT-A-REAL-CODE")).toMatchObject({
      code: "NOT-A-REAL-CODE",
      label: "NOT-A-REAL-CODE",
      category: "unknown",
    });
  });

  it("builds the human confirmation sentence", () => {
    expect(settlementRailLabel("PESONET")).toBe("PESONet");
    expect(
      payoutRouteSentence({
        amount: "₱2,000.00",
        bankCode: "GXCHPHM2XXX",
        settlementRail: "INSTAPAY",
        accountNumber: "09703812037",
      }),
    ).toBe("Send ₱2,000.00 to GCash account 09703812037 via InstaPay.");
  });

  it("resolves local icon assets for rails, providers, the orchestrator, and known institutions", () => {
    expect(orchestratorIconAsset()).toBe(
      "/vendor/x-change/images/payout-destinations/x-change-128.png",
    );
    expect(iconAssetForRail("INSTAPAY")).toBe(
      "/vendor/x-change/images/payout-destinations/rail-instapay-128.png",
    );
    expect(iconAssetForProvider("NetBank")).toBe(
      "/vendor/x-change/images/payout-destinations/netbank-128.png",
    );
    expect(iconAssetForCode("GXCHPHM2XXX")).toBe(
      "/vendor/x-change/images/payout-destinations/gcash-128.png",
    );
    expect(iconAssetForCode("NOT-A-REAL-CODE")).toBeNull();
    expect(destinationInstitution("GXCHPHM2XXX").iconAsset).toBe(
      "/vendor/x-change/images/payout-destinations/gcash-128.png",
    );
  });

  it("pairs one icon (or null) per route segment, in order", () => {
    const icons = payoutRouteIcons({
      bankCode: "GXCHPHM2XXX",
      settlementRail: "INSTAPAY",
      accountNumber: "09173011987",
    });

    expect(icons).toEqual([
      "/vendor/x-change/images/payout-destinations/x-change-128.png",
      "/vendor/x-change/images/payout-destinations/netbank-128.png",
      "/vendor/x-change/images/payout-destinations/rail-instapay-128.png",
      "/vendor/x-change/images/payout-destinations/gcash-128.png",
      null,
    ]);
  });
});
