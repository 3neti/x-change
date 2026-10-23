<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\StoredValue\StoredValueHolderMobile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('allows collection challenges for an OTP-bound contact and detects changed mobile', function (): void {
    $contact = Contact::fromPhoneNumber(phone('09173011987', 'PH'));
    $verifiedMobile = '639173011987';
    $binding = new StoredValueHolderBinding;
    $binding->holder_authority_reference = 'stored-value-holder:verified-contact:'.hash_hmac(
        'sha256',
        $verifiedMobile,
        (string) config('app.key'),
    );
    $binding->setRelation('holder', $contact);

    expect(app(StoredValueHolderMobile::class)->verified($binding))->toBe($verifiedMobile);

    $contact->mobile = '09170000000';
    $contact->save();

    expect(fn () => app(StoredValueHolderMobile::class)->verified($binding))
        ->toThrow(NotFoundHttpException::class);
});
