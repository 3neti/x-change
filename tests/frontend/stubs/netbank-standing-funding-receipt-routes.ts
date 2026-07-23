export const approve = (reference: string) => ({
    url: `/x/cockpit/funding/standing-addresses/netbank/receipts/${reference}/approve`,
    method: 'post',
});
