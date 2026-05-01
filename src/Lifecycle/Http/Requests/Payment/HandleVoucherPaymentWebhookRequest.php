<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class HandleVoucherPaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '*' => ['sometimes'],
        ];
    }

    public function payload(): array
    {
        return $this->all();
    }
}
