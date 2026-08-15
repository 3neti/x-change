<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final class ApprovePartnerApiProductionMandateRequest extends FormRequest
{
    public function authorize(PartnerApiOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), PartnerApiOperatorCapability::ApproveProductionClients);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirm_snapshot' => ['accepted']];
    }
}
