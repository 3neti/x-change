export type CompiledFormPayload = {
    code: string | null | undefined;
    values: Record<string, unknown>;
};

export function buildCompiledFormPayload(
    code: string | null | undefined,
    values: Record<string, unknown>,
): CompiledFormPayload {
    return {
        code,
        values,
    };
}
