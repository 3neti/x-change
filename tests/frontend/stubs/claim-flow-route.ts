export const store = Object.assign(
    (code: string | number) => ({
        url: `/x/claim/${encodeURIComponent(String(code))}/flows`,
        method: 'post' as const,
    }),
    {
        url: (code: string | number) =>
            `/x/claim/${encodeURIComponent(String(code))}/flows`,
    },
);
