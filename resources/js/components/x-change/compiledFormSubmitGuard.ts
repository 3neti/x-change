export function shouldSubmitCompiledForm(
    hasCompiledForm: boolean,
    isValid: boolean,
): boolean {
    return !hasCompiledForm || isValid;
}
