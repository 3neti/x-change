<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\StoredValue;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoredValueHolderMobile
{
    public function verified(StoredValueHolderBinding $binding): string
    {
        $holder = $binding->holder;

        if (! $holder instanceof Model) {
            throw new NotFoundHttpException;
        }

        $mobile = MobileNumber::normalize($holder->getRawOriginal('mobile'));

        if ($mobile === null) {
            throw new NotFoundHttpException;
        }

        if ($holder instanceof Contact) {
            $authority = 'stored-value-holder:verified-contact:'.hash_hmac('sha256', $mobile, (string) config('app.key'));

            if (! hash_equals((string) $binding->holder_authority_reference, $authority)) {
                throw new NotFoundHttpException;
            }

            return $mobile;
        }

        if ($holder->getAttribute('mobile_verified_at') === null) {
            throw new NotFoundHttpException;
        }

        return $mobile;
    }
}
