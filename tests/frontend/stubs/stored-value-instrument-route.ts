type RouteOptions = {
    query?: Record<string, string | number>;
};

const StoredValueInstrumentPageController = (
    args: { instrument: string },
    options?: RouteOptions,
) => {
    const query = new URLSearchParams(
        Object.entries(options?.query ?? {}).map(([key, value]) => [key, String(value)]),
    ).toString();
    const url = `/x/balances/reusable/${args.instrument}`;

    return {
        url: query === '' ? url : `${url}?${query}`,
        method: 'get' as const,
    };
};

export default StoredValueInstrumentPageController;
