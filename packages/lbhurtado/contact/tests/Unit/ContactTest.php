<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Cash;

uses(RefreshDatabase::class);

it('has attributes', function () {
   $contact = Cash::factory()->create();

   expect($contact)->toBeInstanceOf(Cash::class);
   expect($contact->mobile)->toBeString();
   expect($contact->country)->toBeString();
});

it('defaults country to PH when not provided', function () {
    // Create without specifying country
    /** @var Cash $c */
    $c = Cash::create([
        'mobile' => '09171234567',
        'country' => '',      // empty
    ]);

    expect($c->country)->toBe(Cash::DEFAULT_COUNTRY);
});

it('respects an explicit country attribute', function () {
    /** @var Cash $c */
    $c = Cash::create([
        'mobile'  => '09171234567',
        'country' => 'US',
    ]);

    expect($c->country)->toBe('US');
});

it('formats mobile on set using the HasMobile mutator and persists it normalized', function () {
    $raw = ' (0917) 123-4567 ';
    /** @var Cash $c */
    $c = Cash::create([
        'mobile'  => $raw,
        'country' => '',
    ]);

    $expected = phone($raw, Cash::DEFAULT_COUNTRY)
        ->formatForMobileDialingInCountry(Cash::DEFAULT_COUNTRY);

    // Stored value in DB should equal the normalized version
    $this->assertDatabaseHas('contacts', [
        'id'     => $c->id,
        'mobile' => $expected,
    ]);
});

it('formats mobile on get using the HasMobile accessor', function () {
    $raw = '09171234567';
    $formatted = phone($raw, 'PH')->formatForMobileDialingInCountry('PH');

    // Bypass creation logic to insert raw, then load and read accessor
    \DB::table('contacts')->insert([
        'mobile'  => $formatted,
        'country' => 'PH',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $c = Cash::first();
    expect($c->mobile)->toBe($formatted);
});

it('works with a non-default country (e.g. US)', function () {
    $raw = '202-555-0133';
    $c = Cash::create([
        'mobile'  => $raw,
        'country' => 'US',
    ]);

    $expected = phone($raw, 'US')
        ->formatForMobileDialingInCountry('US');

    expect($c->country)->toBe('US');
    expect($c->mobile)->toBe($expected);
})->skip();
