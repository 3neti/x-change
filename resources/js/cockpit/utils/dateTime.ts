const relativeTime = new Intl.RelativeTimeFormat('en', {
    numeric: 'auto',
});

export function formatRelativeTime(
    value: string | null | undefined,
    now: number,
): string | null {
    if (!value) {
        return null;
    }

    const timestamp = Date.parse(value);

    if (!Number.isFinite(timestamp)) {
        return null;
    }

    const seconds = (timestamp - now) / 1_000;
    const absoluteSeconds = Math.abs(seconds);
    const [divisor, unit] =
        absoluteSeconds < 60
            ? [1, 'second' as const]
            : absoluteSeconds < 3_600
              ? [60, 'minute' as const]
              : absoluteSeconds < 86_400
                ? [3_600, 'hour' as const]
                : absoluteSeconds < 2_592_000
                  ? [86_400, 'day' as const]
                  : absoluteSeconds < 31_536_000
                    ? [2_592_000, 'month' as const]
                    : [31_536_000, 'year' as const];

    return relativeTime.format(Math.round(seconds / divisor), unit);
}

export function formatAbsoluteTime(
    value: string | null | undefined,
): string {
    if (!value || !Number.isFinite(Date.parse(value))) {
        return 'Unavailable';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
