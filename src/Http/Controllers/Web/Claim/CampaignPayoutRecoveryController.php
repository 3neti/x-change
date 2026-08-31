<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\Web\Claim\SubmitCampaignPayoutRecoveryRequest;
use LBHurtado\XChange\Http\Requests\Web\Claim\VerifyCampaignPayoutRecoveryRequest;
use LBHurtado\XChange\Services\Campaigns\CampaignPayoutRecoveryService;

final class CampaignPayoutRecoveryController extends Controller
{
    public function start(
        string $code,
        CampaignPayoutRecoveryService $recoveries,
    ): RedirectResponse {
        $grant = $recoveries->findForCodeOrFail($code);
        $recoveries->start($grant);

        return back()->with('status', 'A verification code was sent to the beneficiary mobile.');
    }

    public function verify(
        VerifyCampaignPayoutRecoveryRequest $request,
        string $code,
        CampaignPayoutRecoveryService $recoveries,
    ): RedirectResponse {
        $grant = $recoveries->findForCodeOrFail($code);
        $recoveries->verify($grant, (string) $request->validated('code'));

        return back()->with('status', 'Mobile verified. Review the corrected payout destination.');
    }

    public function submit(
        SubmitCampaignPayoutRecoveryRequest $request,
        string $code,
        CampaignPayoutRecoveryService $recoveries,
    ): RedirectResponse {
        $grant = $recoveries->findForCodeOrFail($code);
        $result = $recoveries->submit(
            grant: $grant,
            bankCode: (string) $request->validated('bank_code'),
            accountNumber: (string) $request->validated('account_number'),
            mobile: $this->optionalString($request->validated('mobile')),
        );

        return back()->with(
            'status',
            ($result['status'] ?? null) === 'succeeded'
                ? 'The corrected payout was completed.'
                : 'The corrected payout was submitted for provider verification.',
        );
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
