<?php

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Event;

it('tests confirm-deposit controller without irrelevant implementation details', function () {
    // Fake events
    Event::fake();

    // Mock gateway
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $this->app->instance(PaymentGatewayInterface::class, $gateway);

    // Define payload
    $payload = [
        'alias' => 'TEST_ALIAS',
        'amount' => 1000,
        'channel' => 'TestChannel',
        'commandId' => 12345,
        'externalTransferStatus' => 'SUCCESS',
        'operationId' => 67890,
        'productBranchCode' => '1010',
        'recipientAccountNumber' => '09181234567',
        'recipientAccountNumberBankFormat' => '09181234567',
        'referenceCode' => '09171234567',
        'referenceNumber' => 'REF12345',
        'registrationTime' => '2023-09-27 12:00:00',
        'remarks' => 'Some remarks',
        'sender' => [
            'accountNumber' => '123456789',
            'institutionCode' => 'NBANK',
            'name' => 'Test Sender',
        ],
        'transferType' => 'P2M',
        'merchant_details' => [
            'merchant_code' => 'A',
            'merchant_account' => '09171234567',
        ],
    ];

    // Mock `confirmDeposit` and ensure it’s called
    $gateway->shouldReceive('confirmDeposit')
        ->once()
        ->with(Mockery::on(fn ($validated) => $validated === $payload))
        ->andReturn(true); // Just return success

    // Act
    $response = $this->postJson(route('confirm-deposit'), $payload);

    // Assert response status
    $response->assertNoContent();
});
