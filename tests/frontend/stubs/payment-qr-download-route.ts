export const download = Object.assign(
    (parameters: { code: string | number; attempt: string | number }) => ({
        url: `/x/pay/${encodeURIComponent(String(parameters.code))}/attempts/${encodeURIComponent(String(parameters.attempt))}/qr`,
        method: 'get' as const,
    }),
    {
        url: (parameters: {
            code: string | number;
            attempt: string | number;
        }) =>
            `/x/pay/${encodeURIComponent(String(parameters.code))}/attempts/${encodeURIComponent(String(parameters.attempt))}/qr`,
    },
);
