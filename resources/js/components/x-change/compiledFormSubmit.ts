import type { CompiledFormPayload } from '@/components/x-change/compiledFormPayload';
import { shouldSubmitCompiledForm } from '@/components/x-change/compiledFormSubmitGuard';

export type CompiledFormSubmitIntent =
    | 'blocked'
    | 'submit';

export type CompiledFormSubmitEvent =
    | {
    intent: 'blocked';
    submitting: false;
    payload: null;
}
    | {
    intent: 'submit';
    submitting: true;
    payload: CompiledFormPayload;
};

export function resolveCompiledFormSubmitIntent(
    hasCompiledForm: boolean,
    isValid: boolean,
): CompiledFormSubmitIntent {
    return shouldSubmitCompiledForm(hasCompiledForm, isValid)
        ? 'submit'
        : 'blocked';
}

export function resolveCompiledFormSubmitEvent(
    hasCompiledForm: boolean,
    isValid: boolean,
    payload: CompiledFormPayload,
): CompiledFormSubmitEvent {
    const intent = resolveCompiledFormSubmitIntent(hasCompiledForm, isValid);

    if (intent === 'blocked') {
        return {
            intent,
            submitting: false,
            payload: null,
        };
    }

    return {
        intent,
        submitting: true,
        payload,
    };
}
