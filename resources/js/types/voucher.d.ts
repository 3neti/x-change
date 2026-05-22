export interface RequiredInput {
    value: string;
    label: string;
}

export interface InspectInstructions {
    voucher_type?: 'redeemable' | 'payable' | 'settlement';
    amount?: number;
    currency?: string;
    formatted_amount?: string;
    target_amount?: number | null;
    formatted_target_amount?: string | null;
    required_inputs?: RequiredInput[];
    expires_at?: string | null;
    starts_at?: string | null;
    validation?: {
        has_secret?: boolean;
        is_assigned?: boolean;
        assigned_mobile_masked?: string | null;
    };
    rider?: {
        message?: string;
        url?: string;
        splash?: string;
    };
    cash?: {
        amount: number;
        currency: string;
        settlement_rail?: string | null;
        validation?: Record<string, any>;
    };
    inputs?: {
        fields: string[];
    };
    feedback?: {
        email?: string | null;
        mobile?: string | null;
        webhook?: string | null;
    };
    count?: number;
    prefix?: string;
    mask?: string;
    ttl?: string | null;
}

export interface PreviewPolicy {
    enabled: boolean;
    scope: 'full' | 'requirements_only' | 'none';
    message?: string;
}

export interface RiderContent {
    enabled: boolean;
    type: string;
    content?: string | null;
    meta?: Record<string, any>;
}

export interface RiderStage {
    type: string;
    enabled: boolean;
    key?: string | null;
    payload?: Record<string, any>;
    meta?: Record<string, any>;
}

export interface RiderStageCollection {
    stages?: RiderStage[];
    meta?: Record<string, any>;
}

export interface RiderPreviewPayload {
    state?: string;
    preClaim?: RiderContent | null;
    stages?: RiderStageCollection | null;
    meta?: Record<string, any>;
}
export interface InspectResponse {
    success: boolean;
    code: string;
    status: 'active' | 'redeemed' | 'expired' | 'scheduled' | string;
    metadata: any;
    info: any;
    preview?: PreviewPolicy;
    instructions?: InspectInstructions;
    redeemed_at?: string | null;
    expired_at?: string | null;
    rider?: RiderPreviewPayload | null;
}
