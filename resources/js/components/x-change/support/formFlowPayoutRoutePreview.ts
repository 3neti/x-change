import type { ClaimWorkflowMetadata } from '../formFlowClaimWorkflow';

/**
 * Minimal Form Flow field shape needed to resolve payout route preview
 * state. Deliberately narrower than the full field-definition contract used
 * by `GenericForm.vue`, so this module has no dependency on that component.
 */
export interface FormFlowFieldLike {
    name: string;
    type: string;
}

/**
 * Finds the first field whose `type` matches. Form Flow field *names* are
 * workflow-provided and may vary (`bank_account`, `destination_wallet`,
 * `beneficiary_bank`, ...) while their `type` stays canonical
 * (`bank_account`, `settlement_rail`). Matching by type keeps callers from
 * having to assume a specific field name.
 */
export function findFieldNameByType(
    fields: FormFlowFieldLike[] | null | undefined,
    type: string,
): string | null {
    const field = (fields ?? []).find((candidate) => candidate.type === type);

    return field ? field.name : null;
}

export function hasFieldOfType(
    fields: FormFlowFieldLike[] | null | undefined,
    type: string,
): boolean {
    return findFieldNameByType(fields, type) !== null;
}

/**
 * Resolves the active bank/wallet routing code from form data. Prefers the
 * field whose `type` is `bank_account`; falls back to the conventional
 * `bank_code` / `bank_account` keys only when no typed field is present
 * (e.g. legacy payloads without field metadata).
 */
export function resolvePayoutBankCode(
    fields: FormFlowFieldLike[] | null | undefined,
    formData: Record<string, unknown>,
): string {
    const fieldName = findFieldNameByType(fields, 'bank_account');
    const raw = fieldName
        ? formData[fieldName]
        : (formData.bank_code ?? formData.bank_account);

    return String(raw ?? '').trim();
}

/**
 * Resolves the account number. Form Flow drivers consistently use the
 * canonical `account_number` field name for this value (there is no
 * distinct field `type` for it), so this reads that key directly.
 */
export function resolvePayoutAccountNumber(
    formData: Record<string, unknown>,
): string {
    return String(formData.account_number ?? '').trim();
}

/**
 * Resolves the active settlement rail, or null when nothing has been
 * selected yet. Prefers the field whose `type` is `settlement_rail`; falls
 * back to the conventional `settlement_rail` key when no typed field is
 * present.
 */
export function resolvePayoutSettlementRail(
    fields: FormFlowFieldLike[] | null | undefined,
    formData: Record<string, unknown>,
): string | null {
    const fieldName = findFieldNameByType(fields, 'settlement_rail');
    const raw = fieldName ? formData[fieldName] : formData.settlement_rail;
    const trimmed = String(raw ?? '').trim();

    return trimmed === '' ? null : trimmed;
}

/**
 * Same as `resolvePayoutSettlementRail`, but returns a default rail
 * (INSTAPAY) instead of null when nothing has been selected yet. Useful for
 * display copy where a fallback rail is expected; do not use this for
 * cross-linking inputs (e.g. bank/rail selects) that need to know whether a
 * rail was actually chosen.
 */
export function resolvePayoutSettlementRailOrDefault(
    fields: FormFlowFieldLike[] | null | undefined,
    formData: Record<string, unknown>,
    fallback = 'INSTAPAY',
): string {
    return resolvePayoutSettlementRail(fields, formData) ?? fallback;
}

export interface PayoutRoutePreviewVisibilityInput {
    fields: FormFlowFieldLike[] | null | undefined;
    formData: Record<string, unknown>;
    claimWorkflow: ClaimWorkflowMetadata | null;
    isDisburseFlow: boolean;
}

/**
 * Decides whether the compact "Money route" preview panel should render on
 * a Form Flow payout input screen.
 *
 * Safety rules encoded here:
 * - Explicit claim workflow metadata wins: `requires_authenticated_officer`
 *   (e.g. campaign officer authorization) always suppresses the panel, and
 *   `requires_destination` always allows it.
 * - Absent explicit metadata, falls back to the legacy `isDisburseFlow`
 *   heuristic used elsewhere in the form.
 * - Never renders unless the step actually collects a `bank_account`-typed
 *   field, so specialized workflows that never collect payout destination
 *   fields (account funding, zero-value settlement, ...) never show payout
 *   UI even if other heuristics misfire.
 * - Requires BOTH a resolved bank code AND an account number, so the panel
 *   never shows generic placeholder text (e.g. "Selected destination",
 *   "account provided by redeemer") for an incomplete selection.
 */
export function isPayoutRoutePreviewVisible(
    input: PayoutRoutePreviewVisibilityInput,
): boolean {
    const { fields, formData, claimWorkflow, isDisburseFlow } = input;

    if (claimWorkflow?.requires_authenticated_officer) {
        return false;
    }

    const workflowAllowsDestination =
        claimWorkflow?.requires_destination === true || isDisburseFlow;

    if (!workflowAllowsDestination) {
        return false;
    }

    if (!hasFieldOfType(fields, 'bank_account')) {
        return false;
    }

    const bankCode = resolvePayoutBankCode(fields, formData);
    const accountNumber = resolvePayoutAccountNumber(formData);

    return Boolean(bankCode) && Boolean(accountNumber);
}
