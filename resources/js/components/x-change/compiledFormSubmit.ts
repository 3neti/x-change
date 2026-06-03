import { shouldSubmitCompiledForm } from '@/components/x-change/compiledFormSubmitGuard';

export type CompiledFormSubmitIntent =
    | 'blocked'
    | 'submit';

export function resolveCompiledFormSubmitIntent(
    hasCompiledForm: boolean,
    isValid: boolean,
): CompiledFormSubmitIntent {
    return shouldSubmitCompiledForm(hasCompiledForm, isValid)
        ? 'submit'
        : 'blocked';
}
