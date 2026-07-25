type FundingRoute = {
    (): { url: string; method: 'post' };
    url: () => string;
};

export const store: FundingRoute = Object.assign(
    function store() {
        return {
            url: '/x/cockpit/funding/pay-code-inspections',
            method: 'post' as const,
        };
    },
    {
        url: () => '/x/cockpit/funding/pay-code-inspections',
    },
);
