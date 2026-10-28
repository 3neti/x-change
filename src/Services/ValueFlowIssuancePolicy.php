<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Execution\ValueFlowAddressResolver;
use Throwable;

final readonly class ValueFlowIssuancePolicy
{
    public function __construct(private ValueFlowAddressResolver $addresses) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $supported = data_get($input, 'execution.driver') === 'stored_value'
            || in_array(data_get($input, 'voucher_type'), ['payable', 'settlement'], true);
        $flow = $input['value_flow'] ?? data_get($input, 'execution.metadata.value_flow');
        unset($input['value_flow']);
        if (! $supported || $flow === null) {
            return $input;
        }
        if (! is_array($flow) || array_diff(array_keys($flow), ['source', 'destination']) !== []) {
            throw ValidationException::withMessages(['value_flow' => 'Choose a valid value destination.']);
        }
        foreach ($flow as $role => $address) {
            if (! is_string($address) || trim($address) === '') {
                throw ValidationException::withMessages(['value_flow.'.$role => 'A destination address is required.']);
            }
            $address = trim($address);
            if (str_starts_with($address, 'instapay.') && $role === 'destination') {
                try {
                    $this->addresses->instapayDestination($address);
                } catch (Throwable) {
                    throw ValidationException::withMessages(['value_flow.destination' => 'Check the receiving institution and account number.']);
                }
            } elseif (preg_match('/^account\.mobile:09\d{9}\.client_funds$/', $address) !== 1) {
                throw ValidationException::withMessages(['value_flow.'.$role => 'Choose a supported value flow address.']);
            }
            $flow[$role] = $address;
        }
        Arr::set($input, 'execution.metadata.value_flow', $flow);

        return $input;
    }
}
