<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final class StoreTreasuryInstitutionFundClassificationRequest extends FormRequest
{
    public function authorize(TreasuryOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), TreasuryOperatorCapability::RequestInstitutionFunds);
    }

    public function rules(): array
    {
        return [
            'evidence_operation_reference' => ['required', 'string', 'max:191'],
            'ownership_basis' => ['required', 'string', 'max:255'],
            'idempotency_reference' => ['required', 'string', 'max:160'],
        ];
    }
}
