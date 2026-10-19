import { usePage } from '@inertiajs/vue3';
import {
    actionLabel,
    presentationForScreen,
    type ActionRole,
    type FormFlowPresentation,
} from '@/pages/form-flow/core/components/formFlowPresentation';

export function useClaimActionLabel(
    screenOverride?: () => string,
): (role: ActionRole, fallback: string, processing?: boolean) => string {
    const page = usePage();
    return (role, fallback, processing = false) => {
        const screen =
            screenOverride?.() ??
            (
                {
                    Entry: 'entry',
                    Confirm: 'confirmation',
                    Success: 'success',
                    Approval: 'approval',
                    AuthRequired: 'auth_required',
                    Error: 'error',
                    Payment: 'payment',
                    PaymentHandoff: 'payment_handoff',
                } as Record<string, string>
            )[(page.component ?? '').split('/').pop() ?? ''];
        return actionLabel(
            presentationForScreen(
                page.props?.claim_ui as FormFlowPresentation | null,
                screen,
            ),
            role,
            fallback,
            processing,
        );
    };
}
