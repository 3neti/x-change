export const store = () => ({
    url: '/x/cockpit/campaigns',
    method: 'post' as const,
});

export const show = (worksheet: string) => ({
    url: `/x/cockpit/campaigns/${worksheet}`,
    method: 'get' as const,
});
