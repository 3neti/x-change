type CheckArguments = {
    code: string | number;
    attempt: string | number | { reference: string | number };
};

export const store = Object.assign(
    (args: CheckArguments) => ({
        url: store.url(args),
        method: 'post' as const,
    }),
    {
        url: (args: CheckArguments) => {
            const attempt =
                typeof args.attempt === 'object'
                    ? args.attempt.reference
                    : args.attempt;

            return `/x/pay/${encodeURIComponent(String(args.code))}/attempts/${encodeURIComponent(String(attempt))}/checks`;
        },
    },
);
