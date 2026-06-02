export type CompiledClaimFormPayload = {
    code: string;
    values: Record<string, unknown>;
};

export type CompiledClaimFormSubmissionPayload = {
    code: string;
    inputs: Record<string, unknown>;
};

export function toCompiledClaimFormSubmissionPayload(
    payload: CompiledClaimFormPayload
): CompiledClaimFormSubmissionPayload {
    return {
        code: payload.code,
        inputs: payload.values,
    };
}
