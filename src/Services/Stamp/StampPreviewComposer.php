<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use Carbon\CarbonInterval;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\Claim\GdRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;

final readonly class StampPreviewComposer
{
    public function __construct(
        private RiderStampClaimCardComposerContract $composer,
        private RiderSplashArtworkSnapshotterContract $artwork,
        private RiderHtmlSanitizer $html,
        private StampProviderIdentity $providers,
        private RiderStampDesignRegistry $designs,
    ) {}

    /**
     * Only visual instruction fields enter the unsaved model. In particular, client-supplied
     * artifact descriptors, provider reservations and artwork snapshots are never trusted.
     *
     * @param  array<string, mixed>  $input
     * @return array{image: string, marker_bounds: array<string, int>|null, requirements: array<string, mixed>, provider_identity: array{code: string, name: string}|null}
     */
    public function preview(array $input, ?string $code = null): array
    {
        if (strlen(json_encode($input, JSON_THROW_ON_ERROR)) > 262144) {
            throw ValidationException::withMessages(['instructions' => 'The Stamp preview is too large.']);
        }

        $request = new GeneratePayCodeRequest($input);
        $rules = array_filter($request->rules(), static fn (string $key): bool => str_starts_with($key, 'rider.') || str_starts_with($key, 'inputs.')
            || str_starts_with($key, 'validation.'), ARRAY_FILTER_USE_KEY);
        $rules = [
            ...$rules,
            'cash' => ['required', 'array'],
            'rider' => ['sometimes', 'nullable', 'array'],
            'inputs' => ['sometimes', 'array'],
            'validation' => ['sometimes', 'nullable', 'array'],
            'claim' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'metadata.custom' => ['sometimes', 'nullable', 'array'],
            'feedback' => ['sometimes', 'nullable', 'array'],
            'inputs.fields' => ['sometimes', 'array', 'max:32'],
            'voucher_type' => ['nullable', 'in:redeemable,payable,settlement'],
            'claim.default_outcome' => ['nullable', 'in:provider_disbursement,account_funding'],
            'cash.amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'cash.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'cash.validation.mobile' => ['nullable', 'string', 'max:255'],
            'cash.validation.payable' => ['nullable', 'string', 'max:255'],
            'cash.validation.mobile_verification' => ['nullable', 'array:driver,enforcement'],
            'cash.validation.mobile_verification.driver' => ['nullable', 'string', 'max:80'],
            'cash.validation.mobile_verification.enforcement' => ['nullable', 'in:strict,soft'],
            'cash.validation.location' => ['nullable', 'string', 'max:1024'],
            'cash.slice_mode' => ['nullable', 'in:fixed,open'],
            'feedback.mobile' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'expires_at' => ['nullable', 'date'],
            'ttl' => ['nullable', 'string', 'max:80', 'regex:/^P(?=.+)[0-9YMWDTHS.]+$/'],
            'metadata.custom.stamp_presentation' => ['nullable', 'array'],
            'rider.redirect_timeout' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'rider.splash_timeout' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'rider.og_source' => ['nullable', 'in:default,message,url,splash'],
            'rider.message' => ['nullable', 'string', 'max:16384'],
            'rider.splash' => ['nullable', 'string', 'max:200000'],
            'rider.url' => ['nullable', 'url:https', 'max:2048'],
        ];
        $validated = Validator::make($input, $rules)->validate();
        // Rebuild from validated leaf paths, rather than accepting unvalidated nested metadata.
        $instructions = ['count' => 1, 'cash' => ['validation' => [
            'secret' => null, 'mobile' => null, 'payable' => null,
            'country' => null, 'location' => null, 'radius' => null,
        ]]];
        foreach (array_keys($rules) as $key) {
            if (! str_contains($key, '*') && ! is_array(data_get($validated, $key)) && Arr::has($validated, $key)) {
                data_set($instructions, $key, data_get($validated, $key));
            }
        }
        foreach (['inputs.fields', 'inputs.requirements', 'cash.validation.mobile_verification', 'cash.validation.location', 'metadata.custom.stamp_presentation'] as $key) {
            if (Arr::has($validated, $key)) {
                data_set($instructions, $key, data_get($validated, $key));
            }
        }
        $outcome = data_get($instructions, 'claim.default_outcome');
        if (is_string($outcome)) {
            data_set($instructions, 'claim.outcomes', [['key' => $outcome]]);
        }
        if (filled(data_get($instructions, 'rider.splash')) && ! in_array(data_get($instructions, 'rider.splash_format'), ['plain', 'markdown'], true)) {
            data_set($instructions, 'rider.splash', $this->html->sanitizeSplash(data_get($instructions, 'rider.splash')));
        }
        $identity = $this->providers->resolve($instructions['provider'] ?? null);
        data_set($instructions, 'metadata.custom.stamp_provider', $identity['code'] ?? 'manual');
        $instructions = $this->designs->normalizeForInstalledVoucherContract($this->designs->materialize($instructions));
        $instructions = $this->artwork->prepare($instructions);
        $voucher = new Voucher;
        $voucher->forceFill(['code' => $code ?? 'AA317', 'metadata' => ['instructions' => $instructions]]);
        try {
            $timing = $voucher->instructions;
            $voucher->expires_at = $timing->expires_at ?? now()->add($timing->ttl ?? CarbonInterval::hours(12));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['instructions' => 'The Stamp instructions or expiry are invalid.']);
        }
        $claimUrl = $code === null ? route('x-change.claim.start') : route('x-change.claim.show', ['code' => $code]);
        if ($this->composer instanceof GdRiderStampClaimShareCardRenderer) {
            $card = $this->composer->preview($voucher, $claimUrl, $code === null);
        } else {
            $composed = $this->composer->compose($voucher, $claimUrl);
            $card = ['contents' => $composed->contents, 'marker_bounds' => null, 'requirements' => ['keys' => [], 'bounds' => null]];
        }

        return [
            'image' => 'data:image/png;base64,'.base64_encode($card['contents']),
            'marker_bounds' => $card['marker_bounds'],
            'requirements' => $card['requirements'],
            'provider_identity' => $identity === null ? null : Arr::only($identity, ['code', 'name']),
        ];
    }
}
