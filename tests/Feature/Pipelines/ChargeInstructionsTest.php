<?php

use App\Pipelines\GeneratedVoucher\ChargeInstructions;
use App\Http\Requests\VoucherInstructionDataRequest;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use App\Repositories\InstructionItemRepository;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use Illuminate\Support\Facades\Config;
use LBHurtado\Voucher\Models\Voucher;
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
        'secret validation only' => [ 'instructions.cash.validation.secret', $values = ['LESLIE'], $tariff = 1.2, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['secret' => $values[0]]],
            'inputs' => ['fields' => []],
            'count' => 1,
        ]],
        'mobile validation only' => [ 'instructions.cash.validation.mobile', $values = ['09467438575'], $tariff = 1.3, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['mobile' => $values[0]]],
            'inputs' => ['fields' => []],
            'count' => 1,
        ]],
        'email feedback only' => [ 'instructions.feedback.email', $values = ['lester@hurtado.ph'], $tariff = 1.7, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => []],
            'feedback' => ['email' => $values[0]],
            'count' => 1,
        ]],
        'message rider only' => [ 'instructions.rider.message', $values = ['The quick brown fox...'], $tariff = 2.0, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => []],
            'rider' => ['message' => $values[0]],
            'count' => 1,
        ]],
        'email inputs only' => [ 'instructions.inputs.fields.email', $values = [true], $tariff = 2.2, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['email']],
            'count' => 1,
        ]],
        'mobile inputs only' => [ 'instructions.inputs.fields.mobile', $values = [true], $tariff = 2.3, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['mobile']],
            'count' => 1,
        ]],
        'name inputs only' => [ 'instructions.inputs.fields.name', $values = [true], $tariff = 2.4, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['name']],
            'count' => 1,
        ]],
        'address inputs only' => [ 'instructions.inputs.fields.address', $values = [true], $tariff = 2.5, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['address']],
            'count' => 1,
        ]],
        'birth date inputs only' => [ 'instructions.inputs.fields.birth_date', $values = [true], $tariff = 2.6, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['birth_date']],
            'count' => 1,
        ]],
        'gmi inputs only' => [ 'instructions.inputs.fields.gross_monthly_income', $values = [true], $tariff = 2.7, [
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => []],
            'inputs' => ['fields' => ['gross_monthly_income']],
            'count' => 1,
        ]],
        'signature inputs only' => [ 'instructions.inputs.fields.signature', $values = [true], $tariff = 2.8, [
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

    $voucher = generate_voucher($instructions);
    $owner = $voucher->owner;
    $preChargingOwnerBalance = (float) $owner->balanceFloat;

    expect($preChargingOwnerBalance)->toBe(1000.0)
        ->and($preChargingInstructionBalance)->toBe(0.0);

    /** Act: Apply the charge manually */
    $pipe = new ChargeInstructions;
    $result = $pipe->handle($voucher, fn ($v) => $v);

    /** Assert: Voucher unchanged and balances correct */
    expect($result)->toBeInstanceOf($voucher::class)
        ->and($result->is($voucher))->toBeTrue()
        ->and((float) $instruction_item->fresh()->wallet->balanceFloat)->toBe($tariff)
        ->and((float) $owner->balanceFloat)->toBe($preChargingOwnerBalance - $tariff);
})->with('instructions');

/**
 * Helper to validate and normalize instruction data using the form request,
 * and generate a voucher from it.
 */
function generate_voucher(array $voucher_instructions): Voucher
{
    $validated = validator($voucher_instructions, (new VoucherInstructionDataRequest)->rules())->validate();

    $data_array = [
        'cash' => [
            'amount' => $validated['cash']['amount'],
            'currency' => $validated['cash']['currency'],
            'validation' => [
                'secret'   => $validated['cash']['validation']['secret'] ?? null,
                'mobile'   => $validated['cash']['validation']['mobile'] ?? null,
                'country'  => $validated['cash']['validation']['country'] ?? null,
                'location' => $validated['cash']['validation']['location'] ?? null,
                'radius'   => $validated['cash']['validation']['radius'] ?? null,
            ],
        ],
        'inputs' => [
            'fields' => $validated['inputs']['fields'] ?? null,
        ],
        'feedback' => [
            'email'   => $validated['feedback']['email'] ?? null,
            'mobile'  => $validated['feedback']['mobile'] ?? null,
            'webhook' => $validated['feedback']['webhook'] ?? null,
        ],
        'rider' => [
            'message' => $validated['rider']['message'] ?? '',
            'url'     => $validated['rider']['url'] ?? '',
        ],
        'count'      => $validated['count'],
        'prefix'     => $validated['prefix'] ?? '',
        'mask'       => $validated['mask'] ?? '',
        'ttl'        => $validated['ttl'] ?? null,
        'starts_at'  => $validated['starts_at'] ?? null,
        'expires_at' => $validated['expires_at'] ?? null,
    ];

    return GenerateVouchers::run(VoucherInstructionsData::from($data_array))->first();
}
