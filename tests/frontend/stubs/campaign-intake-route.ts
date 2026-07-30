const route = (suffix = '') => ({
    url: `/x/cockpit/campaigns/intakes${suffix}`,
    method: 'post',
});

export const store = () => route();
export const update = (intake: string) => ({ ...route(`/${intake}`), method: 'patch' });
export const convert = (intake: string) => route(`/${intake}/conversion`);
export const destroy = (intake: string) => ({ ...route(`/${intake}`), method: 'delete' });
