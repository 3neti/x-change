export function store(reference: string) {
    return {
        url: `/x/cockpit/funding/intents/${reference}/verification-checks`,
        method: 'post',
    };
}
