import type { RawRiderStage } from '@/components/x-rider/types';
import {
    isNonActiveVoucher,
    resolveVoucherStatusDate,
    type ClaimWidgetVoucherLike,
} from '@/components/x-change/claimWidgetVoucherState';

export type ClaimWidgetPreviewViewModelInput = {
    voucherData: ClaimWidgetVoucherLike;
    preClaimVisualStages: RawRiderStage[];
};

export type ClaimWidgetPreviewViewModel = {
    isNonActive: boolean;
    statusDate: unknown;
    hasPreClaimContent: boolean;
};

export function resolveClaimWidgetPreviewViewModel(
    input: ClaimWidgetPreviewViewModelInput,
): ClaimWidgetPreviewViewModel {
    return {
        isNonActive: isNonActiveVoucher(input.voucherData),
        statusDate: resolveVoucherStatusDate(input.voucherData),
        hasPreClaimContent: input.preClaimVisualStages.length > 0,
    };
}
