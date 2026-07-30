type RouteOptions = {
    query?: Record<string, string>;
};

export const quickGenerate = (options?: RouteOptions) => {
    const query = new URLSearchParams(options?.query ?? {}).toString();

    return {
        url:
            query === ''
                ? '/x/cockpit/quick-generate'
                : `/x/cockpit/quick-generate?${query}`,
        method: 'get',
    };
};
