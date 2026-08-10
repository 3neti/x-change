import { describe, expect, it } from "vitest";
import {
  destinationInstitution,
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
});
