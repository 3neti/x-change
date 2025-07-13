// resources/js/composables/useLabelFormatter.ts

export function useLabelFormatter(labelMap: Record<string, string> = {}) {
    function formatLabel(input: string): string {
        return labelMap[input] ?? input
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    }

    return { formatLabel };
}
