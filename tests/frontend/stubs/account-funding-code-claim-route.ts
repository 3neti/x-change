export function store(reference: string) {
    return {
        url: `/x/cockpit/funding/codes/${reference}/claims`,
        method: 'post',
    };
}
