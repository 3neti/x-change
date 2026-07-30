export const update = (worksheet: string) => ({
    url: `/x/cockpit/campaigns/${worksheet}/voucher-blueprint`,
    method: 'put' as const,
});

export default { update };
