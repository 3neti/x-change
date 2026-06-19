export type CompiledFormField = {
    key: string;
    required?: boolean | null;
};

export function hasCompiledFormValue(value: unknown): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'string') {
        return value.trim().length > 0;
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    return true;
}

export function requiredCompiledFormFields(
    fields: CompiledFormField[] | null | undefined,
): CompiledFormField[] {
    return fields?.filter((field) => field.required) ?? [];
}

export function missingRequiredCompiledFormFields(
    fields: CompiledFormField[] | null | undefined,
    values: Record<string, unknown>,
): CompiledFormField[] {
    return requiredCompiledFormFields(fields).filter((field) =>
        !hasCompiledFormValue(values[field.key])
    );
}

export function isCompiledFormValid(
    fields: CompiledFormField[] | null | undefined,
    values: Record<string, unknown>,
): boolean {
    return missingRequiredCompiledFormFields(fields, values).length === 0;
}
