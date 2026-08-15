<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final class StoreTreasuryReconciliationRunRequest extends FormRequest
{
    public function authorize(TreasuryOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), TreasuryOperatorCapability::RequestReconciliation);
    }

    public function rules(): array
    {
        return [
            'connection_reference' => ['required', 'string', 'max:191'],
            'purpose' => ['required', 'string', 'max:255'],
            'idempotency_reference' => ['required', 'string', 'max:160'],
        ];
    }
}
