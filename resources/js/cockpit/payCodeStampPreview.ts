import type {
    RiderStampPreview,
    RiderStampPreviewSource,
} from './riderStampPreview';
import type { XChangeQrArtifactData } from '../components/x-change/qrArtifacts';

export type PayCodeStampPreviewState = Readonly<{
    stampInstructions?: Record<string, unknown>;
    amount: string | number;
    currency: string;
    provider: string;
    recipient: string;
    purpose: string;
    claimOutcome: 'provider_disbursement' | 'account_funding';
    voucherType: 'redeemable' | 'payable' | 'settlement';
    expiry: string;
    instructionKeys: string[];
    hasRiderDesign: boolean;
    riderDesignSource: RiderStampPreviewSource;
    riderDesignDocument: string;
    riderStamp: RiderStampPreview;
    stampPresentation: Record<string, unknown> | null;
    claimEntryArtifact: XChangeQrArtifactData | null;
    quantity: string | number;
}>;
