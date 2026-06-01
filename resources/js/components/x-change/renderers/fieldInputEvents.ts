export function inputElementValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
}

export function textareaElementValue(event: Event): string {
    return (event.target as HTMLTextAreaElement).value;
}

export function selectElementValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
}

export type EmitFieldValue = (event: 'update:value', value: unknown) => void;

export function emitInputElementValue(
    emit: EmitFieldValue,
    event: Event
): void {
    emit('update:value', inputElementValue(event));
}

export function emitTextareaElementValue(
    emit: EmitFieldValue,
    event: Event
): void {
    emit('update:value', textareaElementValue(event));
}

export function emitSelectElementValue(
    emit: EmitFieldValue,
    event: Event
): void {
    emit('update:value', selectElementValue(event));
}
