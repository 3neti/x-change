export default function riderLibraryPinRoute(reference: string) {
    return {
        url: `/x/cockpit/rider-library/${reference}/pin`,
        method: 'patch',
    };
}
