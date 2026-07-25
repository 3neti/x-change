<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestAccess;

final class ClaimReviewedFundingPayCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $fundingRequest = $this->route('fundingRequest');

        if ($actor === null || $fundingRequest === null) {
            return false;
        }

        try {
            app(FundingRequestAccess::class)->authorizeOwner(
                $fundingRequest,
                $actor,
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'currency' => ['prohibited'],
            'outcome' => ['prohibited'],
            'recipient' => ['prohibited'],
        ];
    }
}
