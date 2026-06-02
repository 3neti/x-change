import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';

export type CompiledClaimFormPayload = {
    code: string;
    values: Record<string, FormDataConvertible>;
};

export type CompiledClaimFormSubmissionPayload = {
    code: string;
    inputs: Record<string, FormDataConvertible>;
};

export type SubmitCompiledClaimFormOptions = {
    onSuccess?: () => void;
    onError?: (errors: Record<string, unknown>) => void;
    onFinish?: () => void;
};

export function toCompiledClaimFormSubmissionPayload(
    payload: CompiledClaimFormPayload
): CompiledClaimFormSubmissionPayload {
    return {
        code: payload.code,
        inputs: payload.values,
    };
}

export type RawCompiledClaimFormPayload = {
    code: string;
    values: Record<string, unknown>;
};

export function toCompiledClaimFormPayload(
    payload: RawCompiledClaimFormPayload
): CompiledClaimFormPayload {
    return {
        code: payload.code,
        values: payload.values as Record<string, FormDataConvertible>,
    };
}

export function submitCompiledClaimForm(
    payload: CompiledClaimFormPayload,
    options: SubmitCompiledClaimFormOptions = {}
): void {
    router.post(
        '/x/claim',
        toCompiledClaimFormSubmissionPayload(payload),
        {
            preserveScroll: true,
            onSuccess: options.onSuccess,
            onError: options.onError,
            onFinish: options.onFinish,
        }
    );
}
