<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Http\Request;

final class ClaimPreviewRequestContext
{
    public function isPublic(Request $request): bool
    {
        return $request->routeIs('x-change.public-auto-generate.claim-previews.*');
    }

    public function routeFamily(Request $request): string
    {
        return $this->isPublic($request)
            ? 'x-change.public-auto-generate.claim-previews'
            : 'x-change.cockpit.quick-generate.claim-previews';
    }

    public function guestScope(Request $request): string
    {
        abort_unless($request->hasSession(), 404);

        return hash_hmac('sha256', 'claim-preview:'.$request->session()->getId(), (string) config('app.key'));
    }

    public function storageScope(Request $request): string
    {
        return $this->isPublic($request)
            ? 'public:'.$this->guestScope($request)
            : 'cockpit:'.hash('sha256', $request->user()::class.':'.$request->user()->getAuthIdentifier());
    }
}
