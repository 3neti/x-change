<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;

final class StorePartnerCommissionPayoutBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && app(CommercialOperatorAuthorityContract::class)
                ->allows($operator, CommercialOperatorCapability::RequestCommissionPayouts);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:190'],
            'partner_reference' => ['required', 'string', 'max:160'],
            'provider' => ['required', 'string', 'max:80'],
            'connection_reference' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'string', 'size:3'],
            'period_started_at' => ['required', 'date'],
            'period_ended_at' => ['required', 'date', 'after_or_equal:period_started_at'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
