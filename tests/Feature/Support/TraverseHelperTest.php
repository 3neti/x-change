<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Brick\Money\Money;

uses(RefreshDatabase::class);

class DummyObject
{
    public $foo;

    public function __construct($bar)
    {
        $this->foo = (object)['bar' => $bar];
    }
}

beforeEach(function () {
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);
    $this->system = User::factory()->create(['email' => 'apple@hurtado.ph']);
    $this->system->wallet;
    $this->system->depositFloat(10000);

    $user = User::factory()->create();
    $user->mobile = '09178251991';
    $user->save();

    TopupWalletAction::run($user, 1000);

    $this->actingAs($user);
});

dataset('voucher', function () {
    return [
        [ fn() => GenerateVouchers::run(new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 53.7,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: '123456',
                    mobile: '09171234567',
                    country: 'PH',
                    location: 'Makati City',
                    radius: '1000m'
                )
            ),
            inputs: new InputFieldsData([
                VoucherInputField::EMAIL,
                VoucherInputField::MOBILE,
            ]),
            feedback: new FeedbackInstructionData(
                email: 'feedback@acme.com',
                mobile: '09171234567',
                webhook: 'https://acme.com/webhook',
            ),
            rider: new RiderInstructionData(
                message: 'Welcome!',
                url: 'https://acme.com/rider',
            ),
            count: 1, // Default count
            prefix: null, // Use default prefix from config
            mask: null,   // Use default mask from config
            ttl: null     // Use default TTL (12 hours) from action
        ))->first() ]
    ];
});

it('returns value from array using dot notation', function () {
    $data = ['a' => ['b' => ['c' => 'value']]];
    expect(traverse($data, 'a.b.c'))->toBe('value');
});

it('returns value from object using dot notation', function () {
    $object = new DummyObject('baz');
    expect(traverse($object, 'foo.bar'))->toBe('baz');
});

it('returns default when path is not found', function () {
    $data = ['x' => ['y' => ['z' => 123]]];
    expect(traverse($data, 'x.y.missing', 'fallback'))->toBe('fallback');
});

it('returns full model when key is null', function () {
    $data = ['some' => 'value'];
    expect(traverse($data, null))->toBe($data);
});

it('traverses voucher code', function (Voucher $voucher) {
    expect(traverse($voucher, 'code'))->toBe($voucher->code);
})->with('voucher');

it('traverses cash amount', function (Voucher $voucher) {
    expect(traverse($voucher, 'cash.amount')->isEqualTo($voucher->cash->amount))->toBeTrue();
})->with('voucher');

it('traverses cash validation mobile', function (Voucher $voucher) {
    expect(traverse($voucher, 'instructions.cash.validation.mobile'))->toBe('09171234567');
    expect(traverse($voucher->getData()->instructions, 'cash.validation.mobile'))->toBe('09171234567');
})->with('voucher');

it('traverses feedback webhook', function (Voucher $voucher) {
    expect(traverse($voucher, 'instructions.feedback.webhook'))->toBe('https://acme.com/webhook');
    expect(traverse($voucher->getData()->instructions, 'feedback.webhook'))->toBe('https://acme.com/webhook');
})->with('voucher');

it('traverses rider message', function (Voucher $voucher) {
    expect(traverse($voucher, 'instructions.rider.message'))->toBe('Welcome!');
    expect(traverse($voucher->getData()->instructions, 'rider.message'))->toBe('Welcome!');
})->with('voucher');

it('traverses inputs email, mobile but not signature', function (Voucher $voucher) {
    expect(traverse($voucher, 'instructions.inputs.fields.email'))->toBeTrue();
    expect(traverse($voucher, 'instructions.inputs.fields.mobile'))->toBeTrue();
    expect(traverse($voucher, 'instructions.inputs.fields.signature'))->toBeFalse();
    expect(traverse($voucher->getData()->instructions, 'inputs.fields.email'))->toBeTrue();
    expect(traverse($voucher->getData()->instructions, 'inputs.fields.mobile'))->toBeTrue();
    expect(traverse($voucher->getData()->instructions, 'inputs.fields.signature'))->toBeFalse();
})->with('voucher');

it('returns default for non-existent path', function (Voucher $voucher) {
    expect(traverse($voucher, 'non.existent.path', 'default'))->toBe('default');
})->with('voucher');
