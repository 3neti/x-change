<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final class ExecuteTreasuryAccountGrantRequest extends FormRequest
{
    public function authorize(TreasuryOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), TreasuryOperatorCapability::ExecuteAccountGrants);
    }

    public function rules(): array
    {
        return [];
    }
}
