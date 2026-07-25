export const store = Object.assign(
    (reference: string) => ({
        url: `/x/cockpit/funding/requests/${reference}/pay-code-claims`,
        method: 'post',
    }),
    {
        url: (reference: string) =>
            `/x/cockpit/funding/requests/${reference}/pay-code-claims`,
    },
);
