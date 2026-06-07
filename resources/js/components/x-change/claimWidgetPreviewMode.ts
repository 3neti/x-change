export type ClaimWidgetPreviewMode =
    | 'loading'
    | 'error'
    | 'preview-disabled'
    | 'non-active'
    | 'active'
    | 'empty';

export type ClaimWidgetPreviewModeInput = {
    loading: boolean;
    error: unknown;
    voucherData: Record<string, any> | null | undefined;
    isNonActive: boolean;
};

export function resolveClaimWidgetPreviewMode(
    input: ClaimWidgetPreviewModeInput,
): ClaimWidgetPreviewMode {
    if (input.loading) {
        return 'loading';
    }

    if (input.error) {
        return 'error';
    }

    if (!input.voucherData) {
        return 'empty';
    }

    if (input.voucherData.preview?.enabled === false) {
        return 'preview-disabled';
    }

    if (input.isNonActive) {
        return 'non-active';
    }

    return 'active';
}
