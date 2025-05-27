<?php

use LBHurtado\Voucher\Data\FeedbackInstructionData;

it('parses a comma-delimited input with email, mobile, and webhook', function () {
    $input = "feedback@acme.com, +639171234567, https://acme.com/webhook";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('handles missing fields in the comma-delimited input', function () {
    $input = "feedback@acme.com,https://acme.com/webhook";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBeNull() // Mobile is missing
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('ignores extra, non-resolvable data gracefully', function () {
    $input = "feedback@acme.com,garbage,+639171234567,https://acme.com/webhook";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('resolves email, URL, and mobile regardless of order', function () {
    $input = "+639171234567,https://acme.com/webhook,feedback@acme.com";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com')
        ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});

it('returns null for all properties when input is empty', function () {
    $data = FeedbackInstructionData::fromText("");

    expect($data->email)->toBeNull()
        ->and($data->mobile)->toBeNull()
        ->and($data->webhook)->toBeNull();
});

it('handles invalid input without assigning values', function () {
    $input = "garbage,not_an_email,invalid_url";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBeNull()
        ->and($data->mobile)->toBeNull()
        ->and($data->webhook)->toBeNull();
});

it('handles input with multiple valid values for the same type gracefully', function () {
    $input = "feedback@acme.com,otheremail@domain.com,+639171234567,https://acme.com/webhook";

    $data = FeedbackInstructionData::fromText($input);

    expect($data->email)->toBe('feedback@acme.com') // Only the first valid email is used
    ->and($data->mobile)->toBe('+639171234567')
        ->and($data->webhook)->toBe('https://acme.com/webhook');
});
