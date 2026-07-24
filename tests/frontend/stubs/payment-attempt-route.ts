export const store = Object.assign(
    (code: string | number) => ({
        url: `/x/pay/${encodeURIComponent(String(code))}/attempts`,
        method: 'post' as const,
    }),
    {
        url: (code: string | number) =>
            `/x/pay/${encodeURIComponent(String(code))}/attempts`,
    },
);
