<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;

final class RefreshCockpitFundingLiquidityRequest extends FormRequest
{
    public function authorize(CockpitTreasuryAccessContract $access): bool
    {
        $actor = $this->user();

        return $actor !== null
            && $access->canRefreshProviderLiquidity($actor);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'account_number' => ['prohibited'],
            'provider' => ['prohibited'],
        ];
    }
}
