<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\EnrollmentConsent;
use LBHurtado\XChange\Models\EnrollmentOffer;

it('records a versioned opt-in once without issuing a Pay Code', function (): void {
    config()->set('x-change.app_profile.primary', 'opt-in');
    $owner = actingAsTestUser();

    $this->post(route('x-change.cockpit.enrollments.store'), [
        'title' => 'Starter Policy',
        'consent_version' => '2026-10',
        'consent_statement' => 'I agree to receive information about the Starter Policy.',
    ])->assertRedirect(route('x-change.cockpit.enrollments.index'));

    $offer = EnrollmentOffer::query()->sole();
    expect($offer->owner_id)->toBe((string) $owner->getKey());
    $this->app['auth']->guard()->logout();

    $this->post(route('x-change.enrollment.store', ['reference' => $offer->reference]), [
        'name' => 'Avery Person',
        'mobile' => '09173011987',
        'accepted' => true,
    ])->assertRedirect(route('x-change.enrollment.show', ['reference' => $offer->reference]));
    $this->post(route('x-change.enrollment.store', ['reference' => $offer->reference]), [
        'name' => 'Avery Person',
        'mobile' => '+639173011987',
        'accepted' => true,
    ])->assertRedirect();

    $consent = EnrollmentConsent::query()->sole();
    expect($consent->consent_version)->toBe('2026-10')
        ->and($consent->statement_hash)->toBe(hash('sha256', $offer->consent_statement))
        ->and($consent->mobile_ciphertext)->toBe('+639173011987')
        ->and($consent->getRawOriginal('mobile_ciphertext'))->not->toContain('09173011987');

    $this->actingAs($owner);
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.enrollments.index', ['journey' => 'enrollment_activity']))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/EnrollmentOffers')
        ->assertJsonPath('props.offers.0.consents_count', 1);
});

it('requires affirmative consent and keeps another owner enrollment activity private', function (): void {
    config()->set('x-change.app_profile.primary', 'opt-in');
    $owner = actingAsTestUser();
    $offer = EnrollmentOffer::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'title' => 'Starter Policy',
        'consent_version' => 'v1',
        'consent_statement' => 'I agree to receive information about the Starter Policy.',
    ]);

    $this->post(route('x-change.enrollment.store', ['reference' => $offer->reference]), [
        'name' => 'Avery Person',
        'mobile' => '09173011987',
        'accepted' => false,
    ])->assertSessionHasErrors('accepted');
    expect(EnrollmentConsent::query()->count())->toBe(0);

    actingAsTestUser();
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.enrollments.index'))
        ->assertOk()
        ->assertJsonCount(0, 'props.offers');
});
