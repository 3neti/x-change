<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Services\Claim\ContactKycEvidenceResolver;

it('only exposes approved contact KYC evidence', function (): void {
    $contact = Mockery::mock(Contact::class);
    $contact->shouldReceive('isKycApproved')->once()->andReturn(false);

    expect((new ContactKycEvidenceResolver)->resolve($contact))->toBe([]);
});
