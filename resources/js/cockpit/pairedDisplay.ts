export type DisplayCampaign = {
    reference: string;
    title: string;
    description?: string | null;
    merchant_display_name?: string;
    available?: boolean;
    availability?: string;
};

export type DisplaySession = {
    reference: string;
    status:
        | 'ready'
        | 'claimed'
        | 'awaiting_payment'
        | 'paid'
        | 'completed'
        | 'expired'
        | 'ended'
        | 'review';
    campaign: DisplayCampaign;
    entry_qr_data_uri: string | null;
    public_url?: string | null;
    pay_code?: string | null;
    expires_at: string | null;
    attempt: {
        amount_minor: number;
        currency: string;
        qr_code: {
            mime_type: string | null;
            base64_payload: string | null;
        } | null;
    } | null;
    links: { show: string; end: string; reset: string };
};
