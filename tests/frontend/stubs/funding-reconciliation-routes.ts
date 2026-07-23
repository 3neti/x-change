export function approve(reference: string) {
    return {
        url: `/x/cockpit/funding/reconciliations/${reference}/approve`,
        method: 'post',
    };
}
