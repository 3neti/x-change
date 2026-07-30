export const store = (worksheet: string): { method: 'post'; url: string } => ({
    method: 'post',
    url: `/x/cockpit/campaigns/${worksheet}/authorizations`,
});

export default {
    store,
};
