<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;

final class StoreCommercialProviderCostBatchRequest extends FormRequest
{
    private ?string $amountValidationError = null;

    public function authorize(): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && app(CommercialOperatorAuthorityContract::class)
                ->allows($operator, CommercialOperatorCapability::ReconcileProviderCosts);
    }

    protected function prepareForValidation(): void
    {
        try {
            $this->merge([
                'observed_amount_minor' => MajorCurrencyAmount::toMinor(
                    trim((string) $this->input('observed_amount')),
                ),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->amountValidationError = $exception->getMessage();
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:190'],
            'provider' => ['required', 'string', 'max:80'],
            'connection_reference' => ['required', 'string', 'max:160'],
            'currency' => ['required', 'string', 'size:3'],
            'evidence_type' => ['required', 'string', 'max:80'],
            'evidence_reference' => ['required', 'string', 'max:190'],
            'observed_amount' => ['required', 'string', 'max:32'],
            'observed_amount_minor' => ['required', 'integer', 'min:0'],
            'period_started_at' => ['required', 'date'],
            'period_ended_at' => ['required', 'date', 'after_or_equal:period_started_at'],
            'observed_at' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->amountValidationError !== null) {
                $validator->errors()->add('observed_amount', $this->amountValidationError);
            }
        }];
    }
}
