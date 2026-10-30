<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VoucherIssuancePayloadNormalizer
{
    public function __construct(
        private readonly NamedVoucherSliceService $namedSlices,
        private readonly OnboardingVoucherInstructionPolicy $onboarding,
        private readonly RiderStampDesignRegistry $stampDesigns,
        private readonly StoredValueIssuancePolicy $storedValue,
        private readonly ValueFlowIssuancePolicy $valueFlows,
    ) {}

    public function normalize(array $input): array
    {
        $input = $this->normalizeEnumValues($input);
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2') {
            foreach (['mode', 'pipeline', 'fallback', 'visibility', 'metadata'] as $key) {
                if (array_key_exists($key, $input['execution']) && $input['execution'][$key] === null) {
                    unset($input['execution'][$key]);
                }
            }
        }
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2'
            && (data_get($input, 'pricing') !== null
                || data_get($input, 'campaign') !== null)) {
            throw ValidationException::withMessages([
                'execution.schema' => 'V2 pricing and campaign instructions are not supported for issuance.',
            ]);
        }
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2' && ! empty(data_get($input, 'execution.events'))) {
            $this->assertSupportedPhilhealthEvents($input);
            data_set($input, 'metadata.flow_type', 'settlement');
        }
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2' && ! empty(data_get($input, 'feedback.webhooks'))) {
            $this->assertSupportedWebhookSubscriptions($input);
        }
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2' && data_get($input, 'presentation') !== null) {
            $input = $this->applyV2Presentation($input);
        }
        $input = app(SettlementRepaymentPolicy::class)->normalize($input);
        if (isset($input['fee_bearer'])) {
            if (! in_array($input['fee_bearer'], ['issuer', 'payer'], true)
                || ! in_array($input['voucher_type'] ?? null, ['payable', 'settlement'], true)) {
                throw ValidationException::withMessages([
                    'fee_bearer' => 'Fee bearer must be issuer or payer on a payable or settlement voucher.',
                ]);
            }
        }
        $input = $this->storedValue->normalize($input);
        $input = $this->valueFlows->normalize($input);
        $input = $this->namedSlices->normalizeIssuancePayload($input);
        $input = $this->onboarding->normalize($input);
        $cashValidation = Arr::get($input, 'cash.validation');

        if (! is_array($cashValidation)) {
            Arr::set($input, 'cash.validation', []);
        }

        $input = $this->stampDesigns->materialize($input);
        $input = $this->stampDesigns->normalizeForInstalledVoucherContract($input);

        $voucherType = Arr::get($input, 'voucher_type');
        $flowType = Arr::get($input, 'metadata.flow_type');

        if ($voucherType !== 'payable' && $flowType !== 'collectible') {
            return $input;
        }

        $targetAmount = Arr::get($input, 'target_amount')
            ?? Arr::get($input, 'cash.target_amount')
            ?? Arr::get($input, 'cash.amount')
            ?? Arr::get($input, 'amount');

        if ($targetAmount !== null) {
            Arr::set($input, 'target_amount', $targetAmount);
        }

        Arr::set($input, 'cash.amount', 0);

        return $input;
    }

    private function assertSupportedPhilhealthEvents(array $input): void
    {
        $events = data_get($input, 'execution.events');
        $attest = is_array($events) ? ($events['attest'] ?? null) : null;
        $review = is_array($events) ? ($events['review'] ?? null) : null;
        $settle = is_array($events) ? ($events['settle'] ?? null) : null;
        $requirements = is_array($settle) ? ($settle['requires'] ?? null) : null;
        $execution = data_get($input, 'execution');
        $settlement = data_get($input, 'execution.settlement');
        $supported = data_get($input, 'voucher_type') === 'settlement'
            && in_array(data_get($input, 'metadata.flow_type'), [null, 'settlement'], true)
            && data_get($input, 'metadata.settlement_envelope') === null
            && data_get($input, 'metadata.settlement_checklist') === null
            && data_get($input, 'rules.settlement_mode') === null
            && (float) data_get($input, 'cash.amount', -1) === 0.0
            && (float) data_get($input, 'target_amount', 0) > 0
            && data_get($input, 'execution.driver') === 'settlement_envelope'
            && data_get($input, 'execution.settlement.driver') === 'philhealth-bst'
            && data_get($input, 'execution.settlement.readiness_gate') === 'settleable'
            && is_array($execution) && array_diff(array_keys($execution), ['schema', 'driver', 'settlement', 'value_flow', 'events']) === []
            && is_array($settlement) && array_diff(array_keys($settlement), ['driver', 'version', 'readiness_gate', 'payment_requirement']) === []
            && data_get($input, 'execution.settlement.payment_requirement') === null
            && is_string(data_get($input, 'execution.value_flow.destination'))
            && is_array(data_get($input, 'inputs.fields')) && count(data_get($input, 'inputs.fields')) > 0
            && is_array($events) && in_array(count($events), [2, 3], true)
            && is_array($attest) && array_diff(array_keys($attest), ['actor', 'requires', 'surface', 'authentication']) === []
            && ($attest['actor'] ?? null) === 'beneficiary' && ($attest['requires'] ?? []) === []
            && in_array($attest['surface'] ?? null, [null, 'claim'], true)
            && in_array($attest['authentication'] ?? null, [null, 'anonymous'], true)
            && (count($events) === 2 || (is_array($review)
                && array_diff(array_keys($review), ['actor', 'requires', 'surface', 'authentication']) === []
                && ($review['actor'] ?? null) === 'hospital_reviewer'
                && ($review['requires'] ?? null) === ['attestation_recorded']
                && ($review['surface'] ?? null) === 'claim'
                && ($review['authentication'] ?? null) === 'required'))
            && is_array($settle) && array_diff(array_keys($settle), ['actor', 'requires', 'surface', 'authentication']) === []
            && ($settle['actor'] ?? null) === 'payer' && is_array($requirements)
            && in_array($settle['surface'] ?? null, [null, 'pay'], true)
            && in_array($settle['authentication'] ?? null, [null, 'optional'], true)
            && count(array_filter($requirements, 'is_string')) === 2
            && count($requirements) === 2 && count(array_unique($requirements, SORT_REGULAR)) === 2
            && array_diff($requirements, ['settlement_ready', 'confirmed_payment']) === [];
        if (! $supported) {
            throw ValidationException::withMessages([
                'execution.events' => 'V2 event execution is not implemented for these instructions; issuance is unavailable.',
            ]);
        }
    }

    private function assertSupportedWebhookSubscriptions(array $input): void
    {
        $subscriptions = data_get($input, 'feedback.webhooks');
        $events = data_get($input, 'execution.events');
        if (! is_array($subscriptions) || ! is_array($events)) {
            throw ValidationException::withMessages(['feedback.webhooks' => 'Webhook subscriptions require supported v2 events.']);
        }

        $recordedEvents = [
            'attestation.evidence_captured' => 'attest',
            'settlement.amount_reviewed' => 'review',
            'settlement.payment_collected' => 'settle',
            'settlement.payout_confirmed' => 'settle',
            'settlement.payout_failed' => 'settle',
        ];
        foreach ($subscriptions as $subscription) {
            if (! is_array($subscription) || ! is_string($subscription['url'] ?? null)
                || ! filter_var($subscription['url'], FILTER_VALIDATE_URL)
                || ! in_array(parse_url($subscription['url'], PHP_URL_SCHEME), ['http', 'https'], true)
                || ! is_array($subscription['events'] ?? null) || $subscription['events'] === []) {
                throw ValidationException::withMessages(['feedback.webhooks' => 'Each webhook needs a URL and supported event subscriptions.']);
            }
            foreach ($subscription['events'] as $event) {
                if (! is_string($event) || ! isset($recordedEvents[$event]) || ! isset($events[$recordedEvents[$event]])) {
                    throw ValidationException::withMessages(['feedback.webhooks' => 'The subscribed event is not supported by this instruction.']);
                }
            }
        }
    }

    private function applyV2Presentation(array $input): array
    {
        $presentation = data_get($input, 'presentation');
        if (! is_array($presentation) || array_diff(array_keys($presentation), ['stamp', 'share']) !== []) {
            throw ValidationException::withMessages(['presentation' => 'Invalid v2 presentation instructions.']);
        }
        $stamp = $presentation['stamp'] ?? null;
        if ($stamp !== null) {
            if (! is_array($stamp) || array_diff(array_keys($stamp), ['design_id', 'design_version', 'show_logo', 'show_tagline', 'claim_marker']) !== []) {
                throw ValidationException::withMessages(['presentation.stamp' => 'Invalid v2 stamp instructions.']);
            }
            foreach ($stamp as $key => $value) {
                $existing = data_get($input, 'rider.stamp.'.$key);
                if ($existing !== null && $value !== null && $existing !== $value) {
                    throw ValidationException::withMessages(['presentation.stamp' => 'Stamp instructions conflict with the rider.']);
                }
                if ($value !== null) {
                    data_set($input, 'rider.stamp.'.$key, $value);
                }
            }
        }
        $share = $presentation['share'] ?? null;
        if ($share !== null && (! is_array($share) || array_diff(array_keys($share), ['title', 'description', 'image_source']) !== []
            || ! in_array($share['image_source'] ?? null, [null, 'stamp'], true))) {
            throw ValidationException::withMessages(['presentation.share' => 'Only the Pay Code stamp can be used as the share image.']);
        }

        return $input;
    }

    protected function normalizeEnumValues(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(
            fn (mixed $item): mixed => $this->normalizeEnumValues($item),
            $value,
        );
    }
}
