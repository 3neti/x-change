<?php

use App\Pipelines\GeneratedVoucher\ChargeInstructions;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use App\Repositories\InstructionItemRepository;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Models\Voucher;
use App\Data\CostBreakdownData;
use App\Actions\CalculateCost;
use App\Models\User;

uses(RefreshDatabase::class);

/**
 * Set up system user and wallet before each test.
 */
beforeEach(function () {
    // ✅ Disable ChargeInstructions during test generation
    Config::set('voucher-pipeline.mint-cash', collect(Config::get('voucher-pipeline.mint-cash'))
        ->reject(fn ($pipe) => $pipe === \App\Pipelines\GeneratedVoucher\ChargeInstructions::class)
        ->values()
        ->all()
    );

    $this->artisan('db:seed', ['--class' => \Database\Seeders\InstructionItemSeeder::class]);

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

/**
 * Dataset of instruction keys and their expected values and effects.
 */
dataset('instructions', function () {
    return [
//        'cash amount only' => [ 'cash.amount', $values = [true], $tariff = 20, [
//            'cash' => ['amount' => 50, 'currency' => 'PHP', 'validation' => []],
//            'inputs' => ['fields' => []],
//            'count' => 1,
//        ]],
        'secret validation only' => [ 'cash.validation.secret', $values = ['LESLIE'], $tariff = 1.2, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['secret' => $values[0]]],
            'inputs' => ['fields' => []],
            'count' => 1,
        ]],
        'mobile validation only' => [ 'cash.validation.mobile', $values = ['09467438575'], $tariff = 1.3, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['mobile' => $values[0]]],
            'inputs' => ['fields' => []],
            'count' => 1,
        ]],
        'email feedback only' => [ 'feedback.email', $values = ['lester@hurtado.ph'], $tariff = 1.7, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => []],
            'feedback' => ['email' => $values[0]],
            'count' => 1,
        ]],
        'message rider only' => [ 'rider.message', $values = ['The quick brown fox...'], $tariff = 2.0, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => []],
            'rider' => ['message' => $values[0]],
            'count' => 1,
        ]],
        'email inputs only' => [ 'inputs.fields.email', $values = [true], $tariff = 2.2, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['email']],
            'count' => 1,
        ]],
        'mobile inputs only' => [ 'inputs.fields.mobile', $values = [true], $tariff = 2.3, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['mobile']],
            'count' => 1,
        ]],
        'name inputs only' => [ 'inputs.fields.name', $values = [true], $tariff = 2.4, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['name']],
            'count' => 1,
        ]],
        'address inputs only' => [ 'inputs.fields.address', $values = [true], $tariff = 2.5, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['address']],
            'count' => 1,
        ]],
        'birth date inputs only' => [ 'inputs.fields.birth_date', $values = [true], $tariff = 2.6, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['birth_date']],
            'count' => 1,
        ]],
        'gmi inputs only' => [ 'inputs.fields.gross_monthly_income', $values = [true], $tariff = 2.7, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['gross_monthly_income']],
            'count' => 1,
        ]],
        'signature inputs only' => [ 'inputs.fields.signature', $values = [true], $tariff = 2.8, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['signature']],
            'count' => 1,
        ]],
    ];
});

/**
 * ✅ Charges the correct instruction item based on populated voucher fields.
 * Verifies:
 * - Owner's balance is deducted by tariff
 * - InstructionItem's wallet is credited
 */
test('charge instructions apply correct tariff to instruction wallet', function ($index, $values, $tariff, $instructions) {
    /** Arrange: Get the instruction item and balances */
    $instruction_item = app(InstructionItemRepository::class)->findByIndex($index);
    $preChargingInstructionBalance = (float) $instruction_item->wallet->balanceFloat;

    $voucher = GenerateVouchers::run($instructions)->first();
    $owner = $voucher->owner;
//    dd($owner->depositFloat(10));
    $preChargingOwnerBalance = (float) $owner->balanceFloat;

    $cash = $voucher->instructions->cash->amount;

    expect($preChargingOwnerBalance)->toBe(1000.0 - $cash)
        ->and($preChargingInstructionBalance)->toBe(0.0);

    /** Act: Apply the charge manually */
    $pipe = app(ChargeInstructions::class);
    $result = $pipe->handle($voucher, fn ($v) => $v);
//    dd(Bavix\Wallet\Models\Transaction::all());
//dd($cash, $owner->balanceFloat, $preChargingOwnerBalance, $tariff);
    /** Assert: Voucher unchanged and balances correct */
    expect($result)->toBeInstanceOf($voucher::class)
        ->and($result->is($voucher))->toBeTrue()
        ->and((float) $instruction_item->fresh()->wallet->balanceFloat)->toBe((float) $tariff)
        ->and((float) $owner->balanceFloat)->toBe($preChargingOwnerBalance - $tariff)
    ;

})->with('instructions');

test('calculate cost returns correct component breakdown and total', function ($index, $values, $tariff, $instructions) {
    $data = VoucherInstructionsData::createFromAttribs($instructions);
    $result = CalculateCost::run($data);

    $item = app(InstructionItemRepository::class)->findByIndex($index);

//    $label = $item->meta['description'] ?? $item->name;

    expect($result)->toBeInstanceOf(CostBreakdownData::class)
        ->and($result->breakdown)->toHaveKey($index)
//        ->and($result->breakdown)->toHaveKey($label)
        ->and($result->breakdown[$index])->toBe(0.0)
//        ->and((float) $result->breakdown[$label])->toBe($tariff)
//        ->and($result->total)->toBe((float) ($result->breakdown['cash'] + $result->breakdown[$label]))
    ;
})->with('instructions')->skip();
