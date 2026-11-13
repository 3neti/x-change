<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreOwnerPartnerApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Model;
    }

    public function rules(): array
    {
        $rules = (new StorePartnerApiProductionMandateRequest)->rules();
        $rules['issuer_id'] = ['prohibited'];
        $rules['issuer_type'] = ['prohibited'];
        $rules['acknowledge_secret_once'] = ['accepted'];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $delegated = StorePartnerApiProductionMandateRequest::createFrom($this);
            foreach ($delegated->after() as $check) {
                $check($validator);
            }
        }];
    }
}
