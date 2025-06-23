<?php

use LBHurtado\ModelInput\Tests\Models\User;
use LBHurtado\ModelInput\Enums\Input;

it('can retrieve user inputs', function () {
    // Arrange
    $user = User::factory()->create();
    $user->inputs()->create(['name' => 'mobile', 'value' => '09171234567']);
    $user->inputs()->create(['name' => 'signature', 'value' => 'signature_block']);
    // Act
    $inputs = $user->inputs;

    // Assert
    expect($inputs)->toHaveCount(2);
    expect($inputs->pluck('name')->toArray())->toBe(['signature', 'mobile']);
    expect($inputs->pluck('value')->toArray())->toBe(['signature_block', '09171234567']);
});

it('can set a valid input', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setInput('mobile', '09171234567');

    // Assert
    $this->assertDatabaseHas('inputs', [
        'name' => 'mobile',
        'value' => '639171234567',
        'model_type' => User::class,
        'model_id' => $user->id,
    ]);
});

//it('throws exception when input is invalid', function () {
//    // Arrange
//    $user = User::factory()->create();
//
//    // Mock isValidInput to return false
//    $mock = Mockery::mock(User::class . '[isValidInput]', [$user->getAttributes()]);
//    $mock->shouldReceive('isValidInput')->with('invalid_input', 'value')->andReturn(false);
//    $mock->makePartial();
//
//    // Act & Assert
//    $this->expectException(\phpDocumentor\Reflection\Exception::class);
//    $mock->setStatus('invalid_input', 'value');
//});

//it('can force set a input even if invalid', function () {
//    // Arrange
//    $user = User::factory()->create();
//
//    // Act
//    $user->forceSetInput('invalid_input', 'value');
//
//    // Assert
//    $this->assertDatabaseHas('channels', [
//        'name' => 'invalid_input',
//        'value' => 'value',
//        'model_type' => User::class,
//        'model_id' => $user->id,
//    ]);
//});

it('retrieves inputs in descending order of id', function () {
    // Arrange
    $user = User::factory()->create();
    $user->forceSetInput('mobile', '09171234567');
    $user->forceSetInput('signature', 'signature_block');

    // Act
    $inputs = $user->inputs;

    // Assert
    expect($inputs->pluck('name')->toArray())->toBe(['signature', 'mobile']);
});

it('returns the mobile and signature values from the mobile input', function () {
    // Arrange
    $user = User::factory()->create(); // Create a user

    // Create an email channel for the user
    $user->inputs()->create([
        'name' => 'mobile',
        'value' => '09171234567',
    ]);
    $user->inputs()->create([
        'name' => 'signature',
        'value' => 'signature_block',
    ]);


    // Act
    $mobile = $user->mobile;
    $signature = $user->signature;

    // Assert
    expect($mobile)->toBe('09171234567');
    expect($signature)->toBe('signature_block');
});

it('returns the mobile value from a preloaded inputs relationship', function () {
    // Arrange
    $user = User::factory()->create();

    // Create multiple inputs, including an signature channel
    $user->inputs()->createMany([
        ['name' => 'mobile', 'value' => '09171234567'],
        ['name' => 'signature', 'value' => 'signature_block'],
    ]);

    // Reload the user with the channels relation preloaded
    $user = User::with('inputs')->find($user->id);

    // Act
    $mobile = $user->mobile; // This should use the preloaded relationship instead of querying the database
    // Assert
    expect($mobile)->toBe('09171234567'); // Assert the mobile attribute returns the correct value
});

it('returns false for disallowed input names', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidInput('email', 'lester@hurtado.ph'))->toBeFalse(); // Disallowed name
    expect($user->isValidInput('mobile', '09171234567'))->toBeTrue(); // Allowed name
});

it('returns true for allowed input names with value', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidInput('mobile', '9876543210'))->toBeTrue(); // Allowed name with value
    expect($user->isValidInput('mobile'))->toBeFalse();              // Not allowed name without value
});

it('returns false for allowed input names invalid values', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect($user->isValidInput('mobile', '09876543210'))->toBeTrue(); // Allowed
    expect($user->isValidInput('mobile', 'not valid'))->toBeFalse();  // Not allowed

    expect($user->isValidInput('signature', 'signature_block'))->toBeTrue();
    expect($user->isValidInput('signature', 'invalid'))->toBeFalse();
});

it('allows setting a input using Inputs enum', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setInput(Input::MOBILE, '9876543210');

    // Assert
    expect($user->inputs()->where('name', Input::MOBILE->value)->exists())->toBeTrue();
});

it('throws exception for disallowed input names', function () {
    // Arrange
    $user = User::factory()->create();

    // Act & Assert
    expect(fn () => $user->setInput('email', 'test@example.com'))
        ->toThrow(Exception::class, 'Input name is not valid');
});

it('allows setting a input using a string', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $user->setInput('signature', 'signature_block');

    // Assert
    expect($user->inputs()->where('name', 'signature')->exists())->toBeTrue();
});

it('sets the mobile and signature attributes and stores it as an input', function () {
    // Arrange
    $user = User::factory()->create();

//    dd(config('model-channel.rules'));
    // Act
    $user->mobile = '9876543210'; // Using the setter
    $user->signature = 'signature_block';

    // Assert
    $input = $user->inputs()->where('name', 'mobile')->first();

    // Ensure the channel was created
    expect($input)->not()->toBeNull()
        ->and($input->value)->toBe('639876543210');

    // Assert
    $input = $user->inputs()->where('name', 'signature')->first();

    // Ensure the channel was created
    expect($input)->not()->toBeNull()
        ->and($input->value)->toBe('signature_block');

});

dataset('formatted_mobiles', function () {
    return [
        ['9171234567'],
        ['09171234567'],
        ['0917 123 4567'],
        ['639171234567'],
        ['+639171234567'],
        ['+63 (917) 123-4567'],
    ];
});

it('can find a user based on the mobile input', function (string $mobile) {
    // Arrange
    $user = User::factory()->create(); // Create a user

    // Assign a mobile channel to the user
    $user->mobile = '9171234567';
    $user->save();

    // Act
    $foundUser = User::findByMobile($mobile);

    // Assert
    expect($foundUser)->not()->toBeNull(); // Ensure a user is found
    expect($foundUser->id)->toBe($user->id); // Ensure the correct user is found
})->with('formatted_mobiles');

dataset('inconsistent_mobiles', function () {
    return [
        'E.164 strict match' => ['+639171234567', '639171234567', true],
        'Normalized LIKE match' => ['09171234567', '9171234567', true], // Leading "0" stripped
//        'National LIKE match' => ['+63 (917) 123-4567', '0917 123 4567', true], // Spaces ignored TODO: check mo ito edge case naman
        'Leading 0 stripped' => ['09171234567', '917123456789', true], // Additional digits with match
        'No match' => ['+639171234567', '9123456789', false], // Completely different number
    ];
});

it('properly resolves phone matches with strict and relaxed conditions', function (string $input, string $storedValue, bool $expectedResult) {
    // Arrange
    $user = User::factory()->create();
    $user->inputs()->create([
        'name' => 'mobile',
        'value' => $storedValue,
    ]);

    // Act
    $foundUser = User::findByMobile($input);

    // Assert
    if ($expectedResult) {
        expect($foundUser)->not()->toBeNull();
        expect($foundUser->id)->toBe($user->id)
        ;
    } else {
        expect($foundUser)->toBeNull();
    }
})->with('inconsistent_mobiles')->skip();
