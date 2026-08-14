import { describe, expect, it } from "vitest";
import {
  isPayoutRoutePreviewVisible,
  resolvePayoutAccountNumber,
  resolvePayoutBankCode,
  resolvePayoutSettlementRail,
  resolvePayoutSettlementRailOrDefault,
  type FormFlowFieldLike,
} from "../../resources/js/components/x-change/support/formFlowPayoutRoutePreview";
import {
  destinationInstitution,
  payoutDestinationRouteIcons,
  payoutDestinationRouteSegments,
  payoutRouteSentence,
} from "../../resources/js/components/x-change/support/payoutDestinations";
import type { ClaimWorkflowMetadata } from "../../resources/js/components/x-change/formFlowClaimWorkflow";

const destinationWorkflow: ClaimWorkflowMetadata = {
  key: "voucher_redemption",
  requires_destination: true,
};

const officerAuthorizationWorkflow: ClaimWorkflowMetadata = {
  key: "campaign_officer_authorization",
  requires_authenticated_officer: true,
};

describe("Form Flow payout route preview field resolution", () => {
  it("drives the bank code from a bank_account-typed field regardless of its name", () => {
    const fields: FormFlowFieldLike[] = [
      { name: "beneficiary_bank", type: "bank_account" },
    ];

    expect(
      resolvePayoutBankCode(fields, { beneficiary_bank: "GXCHPHM2XXX" }),
    ).toBe("GXCHPHM2XXX");
  });

  it("falls back to conventional bank_code/bank_account keys when no typed field exists", () => {
    expect(resolvePayoutBankCode([], { bank_code: "GXCHPHM2XXX" })).toBe(
      "GXCHPHM2XXX",
    );
    expect(resolvePayoutBankCode(undefined, { bank_account: "PAPHPHM1XXX" })).toBe(
      "PAPHPHM1XXX",
    );
  });

  it("drives the settlement rail from a settlement_rail-typed field regardless of its name", () => {
    const fields: FormFlowFieldLike[] = [
      { name: "rail_choice", type: "settlement_rail" },
    ];

    expect(
      resolvePayoutSettlementRail(fields, { rail_choice: "PESONET" }),
    ).toBe("PESONET");
    expect(
      resolvePayoutSettlementRailOrDefault(fields, { rail_choice: "PESONET" }),
    ).toBe("PESONET");
  });

  it("returns null (not a misleading default) for an unresolved settlement rail", () => {
    const fields: FormFlowFieldLike[] = [
      { name: "rail_choice", type: "settlement_rail" },
    ];

    expect(resolvePayoutSettlementRail(fields, {})).toBeNull();
    expect(resolvePayoutSettlementRailOrDefault(fields, {})).toBe("INSTAPAY");
  });

  it("resolves account number from the canonical account_number key", () => {
    expect(resolvePayoutAccountNumber({ account_number: "09173011987" })).toBe(
      "09173011987",
    );
    expect(resolvePayoutAccountNumber({})).toBe("");
  });
});

describe("Form Flow payout route preview visibility safety rules", () => {
  const bankAccountFields: FormFlowFieldLike[] = [
    { name: "beneficiary_bank", type: "bank_account" },
    { name: "account_number", type: "text" },
  ];

  it("shows the panel once a differently-named bank field and account number are both present", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: bankAccountFields,
        formData: {
          beneficiary_bank: "GXCHPHM2XXX",
          account_number: "09173011987",
        },
        claimWorkflow: destinationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(true);
  });

  it("hides the panel and avoids misleading placeholders when the account number is missing", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: bankAccountFields,
        formData: { beneficiary_bank: "GXCHPHM2XXX" },
        claimWorkflow: destinationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(false);
  });

  it("hides the panel when the bank/wallet has not been selected yet", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: bankAccountFields,
        formData: { account_number: "09173011987" },
        claimWorkflow: destinationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(false);
  });

  it("hides the panel entirely for a specialized workflow that collects no bank_account field", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: [{ name: "mobile", type: "tel" }],
        formData: { bank_code: "GXCHPHM2XXX", account_number: "09173011987" },
        claimWorkflow: destinationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(false);
  });

  it("never shows payout destination controls for campaign officer authorization", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: bankAccountFields,
        formData: {
          beneficiary_bank: "GXCHPHM2XXX",
          account_number: "09173011987",
        },
        claimWorkflow: officerAuthorizationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(false);
  });

  it("stays hidden for non-destination workflows without explicit metadata or the disburse heuristic", () => {
    expect(
      isPayoutRoutePreviewVisible({
        fields: bankAccountFields,
        formData: {
          beneficiary_bank: "GXCHPHM2XXX",
          account_number: "09173011987",
        },
        claimWorkflow: null,
        isDisburseFlow: false,
      }),
    ).toBe(false);
  });
});

describe("Form Flow payout route preview end-to-end route rendering", () => {
  it("renders the GCash/InstaPay route with local icon assets and text labels", () => {
    const fields: FormFlowFieldLike[] = [
      { name: "beneficiary_bank", type: "bank_account" },
    ];
    const formData = {
      beneficiary_bank: "GXCHPHM2XXX",
      account_number: "09173011987",
    };

    expect(
      isPayoutRoutePreviewVisible({
        fields,
        formData,
        claimWorkflow: destinationWorkflow,
        isDisburseFlow: true,
      }),
    ).toBe(true);

    const bankCode = resolvePayoutBankCode(fields, formData);
    const accountNumber = resolvePayoutAccountNumber(formData);
    const settlementRail = resolvePayoutSettlementRailOrDefault(fields, formData);

    expect(destinationInstitution(bankCode)).toMatchObject({
      label: "GCash",
      shortLabel: "GCash",
      category: "wallet",
    });
    expect(
      payoutDestinationRouteSegments({
        amount: "₱50.00",
        bankCode,
        accountNumber,
        settlementRail,
      }),
    ).toEqual(["₱50.00", "InstaPay", "GCash", "09173011987"]);
    expect(
      payoutDestinationRouteIcons({
        amount: "₱50.00",
        bankCode,
        accountNumber,
        settlementRail,
      }),
    ).toEqual([
      null,
      "/vendor/x-change/images/payout-destinations/rail-instapay-128.png",
      "/vendor/x-change/images/payout-destinations/gcash-128.png",
      null,
    ]);
    expect(
      payoutRouteSentence({ bankCode, accountNumber, settlementRail }),
    ).toBe("Send the money to GCash account 09173011987 via InstaPay.");
  });

  it("keeps Maya Wallet and Maya Bank textually distinct through a differently-named field", () => {
    const fields: FormFlowFieldLike[] = [
      { name: "destination_wallet", type: "bank_account" },
    ];

    expect(
      destinationInstitution(
        resolvePayoutBankCode(fields, { destination_wallet: "PAPHPHM1XXX" }),
      ),
    ).toMatchObject({ shortLabel: "Maya Wallet", category: "wallet" });

    expect(
      destinationInstitution(
        resolvePayoutBankCode(fields, { destination_wallet: "MYDBPHM2XXX" }),
      ),
    ).toMatchObject({ shortLabel: "Maya Bank", category: "bank" });
  });
});
