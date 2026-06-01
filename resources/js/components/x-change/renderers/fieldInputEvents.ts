export function inputElementValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
}

export function textareaElementValue(event: Event): string {
    return (event.target as HTMLTextAreaElement).value;
}

export function selectElementValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
}
