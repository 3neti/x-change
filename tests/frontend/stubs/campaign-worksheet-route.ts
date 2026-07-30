export const store = () => ({
    url: '/x/cockpit/campaigns',
    method: 'post' as const,
});

export const show = (worksheet: string) => ({
    url: `/x/cockpit/campaigns/${worksheet}`,
    method: 'get' as const,
});

export const destroy = (worksheet: string) => ({
    url: `/x/cockpit/campaigns/${worksheet}`,
    method: 'delete' as const,
});
