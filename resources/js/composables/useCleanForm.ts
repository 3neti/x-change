export function useCleanForm() {
    function clean(obj: any, path: string[] = [], excludedPaths: string[] = []): any {
        const currentPath = path.join('.');

        const systemKeys = [
            'isDirty',
            'hasErrors',
            'processing',
            'wasSuccessful',
            'recentlySuccessful',
            '__rememberable',
        ];

        if (excludedPaths.includes(currentPath)) {
            return undefined;
        }

        if (Array.isArray(obj)) {
            const cleaned = obj
                .map((v, i) => clean(v, [...path, String(i)], excludedPaths))
                .filter(v =>
                    v !== null &&
                    v !== '' &&
                    !(Array.isArray(v) && v.length === 0) &&
                    !(typeof v === 'object' && Object.keys(v).length === 0)
                );
            return cleaned.length ? cleaned : undefined;
        }

        if (typeof obj === 'object' && obj !== null) {
            const result: any = {};
            for (const [key, value] of Object.entries(obj)) {
                if (path.length === 0 && systemKeys.includes(key)) continue;

                const cleanedValue = clean(value, [...path, key], excludedPaths);
                if (
                    cleanedValue !== undefined &&
                    cleanedValue !== null &&
                    cleanedValue !== '' &&
                    !(Array.isArray(cleanedValue) && cleanedValue.length === 0) &&
                    !(typeof cleanedValue === 'object' && Object.keys(cleanedValue).length === 0)
                ) {
                    result[key] = cleanedValue;
                }
            }
            return Object.keys(result).length ? result : undefined;
        }

        return obj;
    }

    return { clean };
}
