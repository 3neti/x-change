<?php

use Illuminate\Foundation\Testing\{RefreshDatabase, WithFaker};
use LBHurtado\OmniChannel\Models\SMS;

uses(RefreshDatabase::class, WithFaker::class);

test('sms has attributes', function () {
    $sms = SMS::factory()->create();
    if ($sms instanceof SMS) {
        expect($sms->from)->toBeString();
        expect($sms->to)->toBeString();
        expect($sms->message)->toBeString();
    }
    else dd($sms);
});
