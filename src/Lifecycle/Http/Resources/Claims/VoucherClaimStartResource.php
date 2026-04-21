<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Claims;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherClaimStartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_code' => (string) $this->resource->voucher_code,
                'can_start' => (bool) $this->resource->can_start,
                'entry_route' => (string) $this->resource->entry_route,

                'profile' => [
                    'instrument_kind' => (string) data_get($this->resource, 'profile.instrument_kind'),
                    'redemption_mode' => (string) data_get($this->resource, 'profile.redemption_mode'),
                    'requires_form_flow' => (bool) data_get($this->resource, 'profile.requires_form_flow'),
                    'is_divisible' => (bool) data_get($this->resource, 'profile.is_divisible'),
                    'can_withdraw' => (bool) data_get($this->resource, 'profile.can_withdraw'),
                    'slice_mode' => data_get($this->resource, 'profile.slice_mode') !== null
                        ? (string) data_get($this->resource, 'profile.slice_mode')
                        : null,
                    'driver_name' => (string) data_get($this->resource, 'profile.driver_name'),
                ],

                'requirements' => [
                    'required_inputs' => collect(data_get($this->resource, 'requirements.required_inputs', []))
                        ->map(fn ($value) => (string) $value)
                        ->values()
                        ->all(),
                    'required_validation' => collect(data_get($this->resource, 'requirements.required_validation', []))
                        ->mapWithKeys(fn ($value, $key) => [(string) $key => (bool) $value])
                        ->all(),
                    'has_kyc' => (bool) data_get($this->resource, 'requirements.has_kyc'),
                    'has_otp' => (bool) data_get($this->resource, 'requirements.has_otp'),
                    'has_location' => (bool) data_get($this->resource, 'requirements.has_location'),
                    'has_selfie' => (bool) data_get($this->resource, 'requirements.has_selfie'),
                    'has_signature' => (bool) data_get($this->resource, 'requirements.has_signature'),
                    'has_bio_fields' => (bool) data_get($this->resource, 'requirements.has_bio_fields'),
                ],

                'flow' => [
                    'driver_name' => (string) data_get($this->resource, 'flow.driver_name'),
                    'driver_version' => (string) data_get($this->resource, 'flow.driver_version'),
                    'reference_id_template' => (string) data_get($this->resource, 'flow.reference_id_template'),
                    'on_complete_callback' => (string) data_get($this->resource, 'flow.on_complete_callback'),
                    'on_cancel_callback' => (string) data_get($this->resource, 'flow.on_cancel_callback'),
                    'step_names' => collect(data_get($this->resource, 'flow.step_names', []))
                        ->map(fn ($value) => (string) $value)
                        ->values()
                        ->all(),
                    'step_handlers' => collect(data_get($this->resource, 'flow.step_handlers', []))
                        ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
                        ->all(),
                    'flow_instructions' => data_get($this->resource, 'flow.flow_instructions'),
                ],

                'messages' => collect(data_get($this->resource, 'messages', []))
                    ->map(fn ($value) => (string) $value)
                    ->values()
                    ->all(),
            ],
            'meta' => [],
        ];
    }
}
