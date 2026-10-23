const rawCode = /^[A-Z0-9]+(?:-[A-Z0-9]+)*$/;
const markedCode = /\|\|\s+([A-Z0-9]+(?:-[A-Z0-9]+)*)\s+\|\|/g;

export function parsePayCodeOcr(text: string): string | null {
    const normalized = text.toUpperCase();
    const matches = [...normalized.matchAll(markedCode)];
    if (matches.length !== 1) {
        return null;
    }
    const match = matches[0];
    const before = normalized[match.index! - 1] ?? '';
    const after = normalized[match.index! + match[0].length] ?? '';

    return before === '|' || after === '|' ? null : match[1];
}

export function parsePayCodeInput(input: string): string | null {
    const normalized = input.trim().toUpperCase();
    if (rawCode.test(normalized)) {
        return normalized;
    }

    const match = normalized.match(/^\|\|\s+([A-Z0-9]+(?:-[A-Z0-9]+)*)\s+\|\|$/);

    return match?.[1] ?? null;
}
