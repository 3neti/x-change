<?php

use LBHurtado\PaymentGateway\Data\{DepositResponseData, DepositSenderData};
use LBHurtado\PaymentGateway\Services\SystemUserResolverService;
use LBHurtado\PaymentGateway\Data\DepositMerchantDetailsData;
use LBHurtado\PaymentGateway\Gateways\NetbankPaymentGateway;
use Illuminate\Support\Facades\{Config, Event, Http, Log};
use LBHurtado\PaymentGateway\Actions\TopupWalletAction;
use LBHurtado\PaymentGateway\Events\DepositConfirmed;
use LBHurtado\PaymentGateway\Tests\Models\User;
use Brick\Money\Money;

beforeEach(function () {
    Config::set('account.system_user.identifier', 'system@dev-asiana.io');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Seed or ensure a system user exists
    $system = User::factory()->create([
        'name' => 'System',
        'email' => 'system@dev-asiana.io',
    ]);
    $system->wallet; // Explicitly create the wallet
    $system->depositFloat(10000.00); // Start with a balance of 10,000
});

it('tests the NetbankPaymentGateway generate method', function () {
    // Assume you already have a user with a merchant in your database
    $user = auth()->user(); // The currently authenticated user with a merchant relationship

    // Ensure the user model has the expected merchant relationship
    expect($user->merchant)->not->toBeNull();
    expect($user->merchant->name)->toBe('Test Merchant');
    expect($user->merchant->city)->toBe('Test City');
//    expect($user->merchant->code)->toBe('123ABC'); // Assumes you store the merchant code here

    // Fake HTTP responses for token and QR code endpoints
    Http::fake([
        // Fake token response
        config('disbursement.server.token-end-point') => Http::response([
            'access_token' => 'fake-access-token'
        ], 200),

        // Fake QR code response
        config('disbursement.server.qr-end-point') => Http::response([
            'qr_code' => base64_encode('sample-qr-code-data'),
        ], 200),
    ]);

    // Create an instance of NetbankPaymentGateway
    $gateway = new NetbankPaymentGateway();

    // Define the transaction amount for generating the QR code
    $amount = Money::of(1000, 'PHP'); // Example amount: 1000 PHP

    // Call the generate method to get the QR code response
    $response = $gateway->generate('1234567890', $amount);

    // Assertions for the QR code response
    expect($response)->toStartWith('data:image/png;base64,'); // Ensure it starts with a base64 encoded prefix

    // Assert the token endpoint was called correctly
    Http::assertSent(function ($request) {
        return $request->url() === config('disbursement.server.token-end-point') &&
            $request->hasHeader(
                'Authorization',
                'Basic ' . base64_encode(config('disbursement.client.id') . ':' . config('disbursement.client.secret'))
            );
    });

//    // Assert the QR code endpoint was called correctly with the appropriate payload
//    Http::assertSent(function ($request) use ($user, $amount) {
//        $payload = $request->data();
//        return $request->url() === config('disbursement.server.qr-end-point') &&
//            $payload['merchant_name'] === $user->merchant->name &&
//            $payload['merchant_city'] === $user->merchant->city &&
//            $payload['qr_type'] === ($amount->isZero() ? 'Static' : 'Dynamic') &&
//            $payload['qr_transaction_type'] === 'P2M' &&
//            $payload['destination_account'] === __(':alias:account', [
//                'alias' => config('disbursement.client.alias'),
//                'account' => $user->merchant->code, // Use real merchant code here
//            ]);
//    });
});

it('sends a live QR transaction to Netbank', function () {
    // Fetch the authenticated user (ensure user is set up correctly)
    $user = auth()->user();

    // Ensure the user has a corresponding merchant relationship
    expect($user->merchant)->not->toBeNull();
    expect($user->merchant->name)->not->toBeNull();
    expect($user->merchant->city)->not->toBeNull();

    // Create an instance of NetbankPaymentGateway
    $gateway = new NetbankPaymentGateway();

    // Define the transaction amount (adjust as needed)
    $amount = Money::of(1000, 'PHP'); // Example amount: 1000 PHP

    try {
        // Call the generate method to send the transaction
        $response = $gateway->generate('1234567890', $amount);

        // Log the response for debugging
        dump('QR Code Response:', $response);

        // Assert that it returns a valid base64-encoded QR code string
        expect($response)->toStartWith('data:image/png;base64,');

    } catch (\Throwable $e) {
        // Catch and log any errors returned by Netbank or the gateway
        dump('Error:', $e->getMessage());
        throw $e; // Rethrow the exception if needed for assertion purposes
    }
})->skip();

dataset('user', function () {
    return [
        [fn() => tap(auth()->user(), function (User $user) {
            $user->mobile = '09171234567';
            $user->save();
            $user->wallet; // Explicitly create the wallet
            $user->wallet->refreshBalance();
        })]
    ];
});

dataset('response', function () {
    return [
        [fn() => new DepositResponseData(
            alias: 'TEST_ALIAS',
            amount: 1000, // Deposit amount
            channel: 'TestChannel',
            commandId: 'COMMAND123',
            externalTransferStatus: 'SUCCESS',
            operationId: 'OPID-456',
            productBranchCode: '1010',
            recipientAccountNumber: '09181234567',
            recipientAccountNumberBankFormat: '09181234567',
            referenceCode: '09171234567', // Matches the user's mobile
            referenceNumber: 'REFNO123',
            registrationTime: '2023-09-27 12:00:00',
            remarks: 'Transaction Remark',
            sender: new DepositSenderData(
                accountNumber: '123456789',
                institutionCode: 'NBANK',
                name: 'Test Sender'
            ),
            transferType: 'P2M',
            merchant_details: new DepositMerchantDetailsData(
                merchant_code: 'A',
                merchant_account: '09171234567'
            )
        )]
    ];
});

it('tests confirmDeposit function in NetbankPaymentGateway', function (User $user, DepositResponseData $response) {
    // Arrange
    Event::fake();
    Log::spy();

    // Mock the Transfer instance
    $transfer = \Mockery::mock(\Bavix\Wallet\Models\Transfer::class)->makePartial();

    // Mock the Transaction (deposit) relationship and its attributes
    $transaction = \Mockery::mock(\Bavix\Wallet\Models\Transaction::class);
    $transaction->shouldReceive('getAttribute')
        ->with('payable')
        ->andReturn($user) // Set the transaction's payable relationship to the user
        ->shouldReceive('getAttribute')
        ->with('amount')
        ->andReturn(1000 * 100); // Set the transaction's amount in minor units

    // Optional: Add other attributes if needed
    $transaction->shouldReceive('getAttribute')
        ->with('type')
        ->andReturn('deposit');

    // Inject the mocked transaction into the transfer
    $transfer->setRelation('deposit', $transaction); // Properly mock the deposit relationship

    // Mock the Wallet Action
    TopupWalletAction::shouldRun()
        ->with($user, $response->amount)
        ->andReturn($transfer); // Return the mocked transfer

    $gateway = new NetbankPaymentGateway();

    // Act
    $result = $gateway->confirmDeposit($response->toArray()); // Call the method under test

    // Assert
    expect($result)->toBeTrue(); // Ensure the action returns true

    // Assert the DepositConfirmed event was dispatched with the correct transaction
    Event::assertDispatched(DepositConfirmed::class, function (DepositConfirmed $event) use ($transaction) {
        return
            $event->transaction->getAttribute('payable')->is($transaction->getAttribute('payable'))
            && $event->transaction->getAttribute('amount') === $transaction->getAttribute('amount')
            && $event->transaction->getAttribute('type') === $transaction->getAttribute('type')
            ;// Compare against the actual mocked transaction
    });

    // (Optional) Verify logging
    Log::shouldHaveReceived('info')->once();
})->with('user', 'response');

it('tests confirmDeposit with valid payload and updates balances', function (User $user, DepositResponseData $response) {
    // Arrange
    $system = app(SystemUserResolverService::class)->resolve();
    $system->wallet->refreshBalance();
    $this->withoutExceptionHandling(); // For easier debugging during testing

    expect((float) $user->balanceFloat)->toBe(0.00);

    // Mock the gateway BUT DO NOT mock transferToWallet
    $gateway = new NetbankPaymentGateway(); // No Mockery here; use the actual implementation

    // Act: Call confirmDeposit to test the full integration
    $result = $gateway->confirmDeposit($response->toArray());

    // Assert
    expect($result)->toBeTrue(); // Ensure the confirmDeposit returned true
    $system->wallet->refreshBalance();
    expect((float) $system->balanceFloat)->toBe(9000.00); // System loses 1,000
    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(1000.00); // User gains 1,000
})->with('user', 'response');
