<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use LBHurtado\XChange\Support\Auth\MobileNumber;

class AuthenticateMobileFirstUser
{
    public function __invoke(Request $request): ?Authenticatable
    {
        $mobileCandidates = MobileNumber::candidates($request->string('mobile')->toString());

        if ($mobileCandidates === []) {
            return null;
        }

        $userModel = (string) config('auth.providers.users.model', 'App\\Models\\User');

        /** @var class-string<Model&Authenticatable> $userModel */
        $user = $userModel::query()
            ->where(function (Builder $query) use ($mobileCandidates): void {
                $query->whereIn('mobile', $mobileCandidates);
            })
            ->first();

        if (! $user instanceof Authenticatable) {
            return null;
        }

        if (! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            return null;
        }

        return $user;
    }
}
