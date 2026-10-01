export type XChangeQrArtifactKind =
    'claim_entry' | 'pay_code' | 'campaign_endpoint' | 'qrph_payment';

export type XChangeQrArtifactData = {
    kind: XChangeQrArtifactKind;
    destination: string;
    image_data_uri: string;
    title: string;
    description: string;
    identifier: string | null;
    center_mark: 'pay_code' | 'none';
};

export type XChangePayCodeQrArtifacts = {
    direct_claim: XChangeQrArtifactData;
    claim_entry: XChangeQrArtifactData;
};
