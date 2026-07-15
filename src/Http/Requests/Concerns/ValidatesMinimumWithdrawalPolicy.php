<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;

trait ValidatesMinimumWithdrawalPolicy
{
    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    app(MinimumWithdrawalPolicyResolverContract::class)
                        ->assertIssuancePayload($validator->getData());
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach (Arr::wrap($messages) as $message) {
                            $validator->errors()->add($field, (string) $message);
                        }
                    }
                }
            },
        ];
    }
}
