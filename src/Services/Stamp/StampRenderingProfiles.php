<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XStamp\RenderingProfile;

final class StampRenderingProfiles
{
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function materialize(array $input): array
    {
        $name = data_get($input, 'metadata.custom.stamp_rendering.profile', config('x-change.experience.stamp_rendering.default', 'standard'));
        $snapshot = data_get($input, 'metadata.custom.stamp_rendering');
        $signed = is_array($snapshot) && ($snapshot['schema'] ?? null) === 'x-change.stamp-rendering/1'
            && is_string($snapshot['signature'] ?? null) && is_string($name)
            && hash_equals($this->signature($name), $snapshot['signature']);
        $allowed = config('x-change.experience.stamp_rendering.allowed', ['standard']);
        if (! is_string($name) || RenderingProfile::tryFrom($name) === null || (! $signed && ! in_array($name, $allowed, true))) {
            throw ValidationException::withMessages(['metadata.custom.stamp_rendering.profile' => 'This Stamp rendering profile is unavailable.']);
        }
        data_set($input, 'metadata.custom.stamp_rendering', ['schema' => 'x-change.stamp-rendering/1', 'profile' => $name, 'signature' => $this->signature($name)]);

        return $input;
    }

    private function signature(string $name): string
    {
        return hash_hmac('sha256', 'x-change.stamp-rendering/1|'.$name, (string) config('app.key'));
    }

    public function forVoucher(Voucher $voucher): RenderingProfile
    {
        return RenderingProfile::resolve((string) data_get($voucher, 'instructions.metadata.custom.stamp_rendering.profile', 'standard'));
    }
}
