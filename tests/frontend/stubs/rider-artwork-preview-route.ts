export default function CockpitRiderArtworkPreviewController(): {
    url: string;
    method: 'post';
} {
    return {
        url: '/x/cockpit/quick-generate/artwork-previews',
        method: 'post',
    };
}
