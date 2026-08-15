<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;

final class ApproveProvisioningRequest extends FormRequest
{
    public function authorize(ProvisioningOperatorAuthority $authority): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && $authority->allows($operator, ProvisioningOperatorCapability::Approve);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['confirm_snapshot' => ['required', 'accepted']];
    }
}
